<template>
  <main class="container">
    <header>
      <div class="heading-row">
      <h1>
        <img src="/accessibility-logo.svg" style="max-height: 60px" alt="Accessibility Logo"/>
        ICF Code Mapper
      </h1>
        <Turnstile
            :key="turnstileKey"
            :site-key="TURNSTILE_SITE_KEY"
            v-model="turnstileToken"
        />
        </div>
      <h5>Internationale Klassifikation der Funktionsfähigkeit</h5>
      <p class="small">Dies ist ein Demo-Tool zur Analyse von Texten zu Teilhabestörungen. Nicht für diagnostische
        Zwecke geeignet. Achtung: KI kann Fehler machen.</p>
    </header>

    <!-- Eingabe -->
    <article>
      <header>
        <h2>Text-Eingabe</h2>
        <details>
          <summary>Worum geht es hier überhaupt?</summary>
          <h4>Teilhabestörungen</h4>
          <p>Teilhabestörungen verhindern ein <strong>gleichberechtigtes Leben</strong> in der Gemeinschaft. Sie
            entstehen durch
            Behinderung oder Krankheit, aber auch durch bewußte und unbewußte <strong>Marginalisierung von
              Menschen</strong> aufgrund von
            ethnischer, religiöser und Gender-Zugehörigkeit aber auch durch Aufspaltungen in der Gesellschaft aufgrund
            von Bildung und finanziellem Status.</p>
          <p>Dies geschieht sowohl auf individueller als auch auf institutioneller Ebene. Dieses Projekt soll <strong>Teilhabestörungen
            messbar</strong> machen.</p>

          <h4>Weitere Informationen</h4>
          <ul>
            <li><a
                href="https://renecol.org/rehabilitation/dringender-aufruf-zum-wandel-die-un-berichte-zeigen-systemische-maengel-bei-der-gleichberechtigung-von-menschen-mit-behinderungen/">Systemische
              Ungleichbehandlung von Menschen mit Behinderungen</a></li>
          </ul>
        </details>
        <details>
          <summary>Warum ist das wichtig?</summary>
          <h4>Teilhabestörung und Gesundheit</h4>
          <p>Teilhabestörungen erzeugen per se einen <strong>Krankheitswert</strong>: diese Menschen geht es
            gesundheitlich schlechter und sie sterben statistisch gesehen auch früher.</p>
          <p>Auf individueller Ebene kann jeder davon betroffen sein. Auf Ebene der Gemeinschaft erzeugt die fatale
            gesundheitliche Endstrecke von Teilhabestörungen <strong>höhere Kosten</strong> - diese fallen dann im
            Gesundheitswesen an. Diese Kosten wären vermeidbar.</p>
          <p>Die ICF Klassifikation hilft uns, Teilhabestörungen einzuordnen und gezielt anzugehen. Jedoch ist bisher
            noch keine Übersetzung von Alltagssprache in die ICF Klassifikation gelungen.</p>

          <h4>Weitere Informationen</h4>
          <ul>
            <li><a
                href="https://www.who.int/teams/social-determinants-of-health/equity-and-health/world-report-on-social-determinants-of-health-equity">World
              report on
              social determinants
              of health equity</a></li>
            <li><a href="https://www.euro.who.int/en/publications/abstracts/health-equity-status-report-2019">WHO
              European Health Equity Status Report</a></li>
            <li><a
                href="https://renecol.org/icfx/die-macht-des-einfachen-warum-behandler-und-patienten-intuitiv-im-icf-kontext-uebereinstimmen/">Möglichkeiten
              der ICF</a></li>
          </ul>
        </details>
        <details>
          <summary>Was muss ich machen?</summary>
          <p>Jeder von uns kennt Menschen mit Teilhabestörungen. Kannst du deren Problem (ohne Namensnennung!) in ein
            oder zwei Sätzen zusammenfassen? Gib dies in das Freitext-Feld ein und lass die KI den Text analysieren.
            Danach interessiert uns, ob die KI die richtigen ICF-Items erzeugt hat. Dazu klickst du bitte auf die
            markierten Wörter im Text und gibst mit Daumen hoch/runter ein Zeichen, ob dies zu dem jeweiligen ICF Item
            passt. </p>
          <p>Die Daten bleiben vollständig anonym und werden statistisch ausgewertet. Bitte teile den Link dieser Seite
            auch mit deinen Bekannten, Freunden, Mitarbeitern. Danke!</p>
          <button @click="copyLinkToCLipboard">{{ linkText }}</button>
        </details>
      </header>

      <label for="sentence">Freitext eingeben</label>
      <textarea
          id="sentence"
          v-model="sentence"
          rows="3"
          placeholder="z.B. Ich kann schlecht laufen und schlecht hören."
          :disabled="isLoading || isSuccess"
          :aria-busy="isLoading"
          @keydown.ctrl.enter="handleSubmit"
          @keydown.meta.enter="handleSubmit"
      />

      <button @click="useFakeData" v-if="dev_mode">FakeData</button>

      <button
          :disabled="isLoading || !sentence.trim() || !turnstileToken"
          :aria-busy="isLoading"
          @click="handleSubmit"
      >
        {{ isLoading ? 'Analysiere… (kann bis zu 5 Minuten dauern) ' : 'Analysieren' }}
      </button>

      <button
          :disabled="isLoading || !isSuccess"
          @click="reset">
        Reset
      </button>


      <!-- Pico stylt role="alert" automatisch als Error-Banner -->
      <p v-if="error" role="alert" class="alert-danger">{{ error }}</p>
      <p v-else-if="matches.length=== 0 && isSuccess" role="alert" class="alert">Keine ICF-Codes gefunden. Bitte versuche es mit einer anderen Formulierung oder einem anderen Satz.</p>
    </article>

    <!-- Ergebnis -->
    <article v-if="matches.length">
      <header>
        <h2>Analyse-Ergebnis</h2>
        <p class="small">Bitte bewerte das Analyse-Ergebnis (Click auf die unterstrichenen Wörter oder markiere verpasste Textstellen)! Die Eingaben werden
          zu Forschungszwecken anonym gespeichert.</p>
      </header>
      <IcfResult
          :sentence="sentence"
          :sentence-uuid="sentenceUuid"
          :matches="matches"
          :unique-codes="uniqueCodes"
          :annotated-html="annotatedHtml"
          :dynamic-css="dynamicCss"
          :grouped-matches="groupedMatches"
      />
    </article>

    <!-- Verlauf – Pico stylt <details>/<summary> als Accordion automatisch -->
    <details v-if="history.length">
      <summary>Verlauf ({{ history.length }})</summary>
      <ul>
        <li
            v-for="(entry, i) in history"
            :key="i"
            style="cursor:pointer"
            @click="sentence = entry"
        >
          ↩ {{ entry }}
        </li>
        <li>
          <button @click="history=[]">Verlauf leeren</button>
        </li>
      </ul>
    </details>

    <details>
      <summary>Referenzen / Datenschutz / Impressum / Kontakt / Cookies</summary>
      <ul>
        <li>Verwendete LLM über die API von <a href="https://llm.scads.ai/docs/" target="_blank">SCADS.AI</a>. Es
          erfolgt kein Training der KI mit den eingegebenen Daten.
        </li>
        <li>ICF Icons sind von Tai Takahashi <a href="http://www.icfillustration.com/icfil_eng/top.html">Link zu den
          Illustrationen</a></li>
        <li>
          <h6>Datenschutz kompakt</h6>
          <p>Speicherung der Freitexteingabe, des KI-Analyse-Ergebnisses, Zeitstempel und der Feedback-Eingaben auf
            einem Server in Deutschland. Keine IP-Nummern. Zweck: wissenschaftliche Auswertung. Verantwortlich: siehe
            Kontakt. </p>
          <a href="https://renecol.org/datenschutzerklaerung/">DSGVO-Datenschutzerklärung lang</a></li>
        <li><a href="https://renecol.org/impressum/">Impressum</a></li>
        <li>Kontakt: Follow me on <a href="https://www.linkedin.com/in/peter-bernstein-renecol/">LinkedIn</a>, EMail: <a
            href="mailto:post@renecol.org">post@renecol.org</a></li>
        <li>Source Code auf <a href="https://github.com/bernspe/ifc2text-mapper">GitHub</a></li>
        <li>Diese App verwendet keine Cookies. Lediglich der Verlauf wird lokal im Browser gespeichert.</li>
      </ul>
    </details>

    <footer>

      <div class="footer-content" style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:20px;">
        <div style="display:flex; gap:10px; align-items:center;">
          <img src="/heart-icon.svg" style="height:48px;" alt="Heart Icon"/>
          <span>Wenn dir die App gefällt, teile sie gerne mit deinen Freunden!</span>
        </div>
        <button @click="shareApp"><img src="/share-button-green.svg" style="margin-right: 10px; height:24px;"/> Diese App teilen</button>

      </div>
    </footer>
  </main>
</template>

<script setup lang="ts">
import {ref, watch} from 'vue'
import IcfResult from '~/components/IcfResult.vue'
import {useIcfAnalyzer} from '~/composables/useIcfAnalyzer'
import {Turnstile} from '@sctg/turnstile-vue3';


const TURNSTILE_SITE_KEY = import.meta.env.VITE_TURNSTILE_SITE_KEY as string
const turnstileKey = ref(0)

const dev_mode = import.meta.env.DEV

const {
  sentence, sentenceUuid, matches, isLoading, isSuccess, error,
  history, uniqueCodes, annotatedHtml, dynamicCss, groupedMatches,
  get_example_sentence, get_example_error, analyze, reset
} = useIcfAnalyzer()

const turnstileToken = ref('')
const fakeData = ref(false)
async function handleSubmit() {
  if (!turnstileToken.value || !sentence.value.trim() || isLoading.value || isSuccess.value) return
  await analyze(turnstileToken.value, fakeData.value)
  turnstileToken.value = ''
  turnstileKey.value++;
}

const linkText=ref('Link in die Zwischenablage kopieren')

function copyLinkToCLipboard() {
  navigator.clipboard.writeText('https://icfmapper.renecol.org')
    .then(() => {
      linkText.value = 'Link kopiert!'
      setTimeout(() => linkText.value = 'Link in die Zwischenablage kopieren', 2000)
    })
    .catch(err => {
      console.error('Fehler beim Kopieren des Links: ', err)
      linkText.value = 'Fehler beim Kopieren'
    })
}

function useFakeData() {
  sentence.value = get_example_sentence()
  fakeData.value = true
}

function handleReset() {
  reset()
  fakeData.value=false
}

function shareApp() {
  if (navigator.share) {
    navigator.share({
      title: 'ICF Code Mapper',
      text: 'Entdecke den ICF Code Mapper - ein Tool zur Analyse von Teilhabestörungen! Teile deine Erfahrungen und hilf mit, die Welt inklusiver zu gestalten.',
      url: 'https://icfmapper.renecol.org',
    }).then(() => {
      console.log('App erfolgreich geteilt!');
    }).catch((error) => {
      console.error('Fehler beim Teilen der App:', error);
    });
  } else {
    copyLinkToCLipboard()
  }
}


</script>

<style>
/* Pico CSS Variables überschreiben für ICF-Branding */


:root {
  --pico-font-family-sans-serif: 'DM Sans', system-ui, sans-serif;
  --pico-font-size: 16px;
}

p.small {
  font-size: 0.875em;
  color: #555;
}

.container {
  max-width: 800px;
  margin: 2rem auto;
  padding: 0 1rem;
}

.heading-row {
  display: grid;
  grid-template-columns: auto auto;
  align-items: center;
  gap: 10px;
}

.alert-danger {
  background-color: #feebee;
  color: #b71c1c;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
  margin-top: 1rem;
  background-color: dodgerblue;
  color: white;
}

button {
  margin-right: 0.5rem;
  margin-top: 0.5rem;
}

</style>
