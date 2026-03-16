<template>
  <main class="container">
    <header>
      <h1>
        <img src="/accessibility-logo.svg" style="max-height: 60px" alt="Accessibility Logo"/>
        ICF Code Mapper
      </h1>

      <h5>Internationale Klassifikation der Funktionsfähigkeit</h5>
      <p>Hast du eine Einschränkung, die dich im Alltag hindert? Beschreib sie in einem Satz – und wir zeigen dir, wie
        das internationale Gesundheitssystem sie einordnet.</p>
    </header>

    <!-- Eingabe -->
    <article id="article-input">
      <header>
        <h2>Einschränkung eingeben</h2>
      </header>

      <label for="sentence">Freitext eingeben</label>
      <textarea
          id="sentence"
          v-model="sentence"
          rows="3"
          placeholder="z.B.: Wegen meiner Angststörung kann ich nicht mehr mit dem Bus fahren und bin deshalb fast nie unter Menschen. Zum Glück helfen mir meine Freunde."
          :disabled="isLoading || isSuccess"
          :aria-busy="isLoading"
          @keydown.ctrl.enter="handleSubmit"
          @keydown.meta.enter="handleSubmit"
      />
      <p class="small">Deine Eingabe wird anonym zur Verbesserung der ICF-Analyse genutzt. Kein Account, keine
        E-Mail.</p>
      <button @click="useFakeData" v-if="dev_mode">FakeData</button>

      <button
          :disabled="isLoading || !sentence.trim() || !turnstileToken"
          :aria-busy="isLoading"
          @click="handleSubmit"
      >
        {{ isLoading ? 'Analysiere… (kann bis zu 5 Minuten dauern) ' : 'Analysieren' }}
      </button>

      <button class="secondary" v-if="isSuccess"
              @click="reset">
        Reset
      </button>


      <!-- Pico stylt role="alert" automatisch als Error-Banner -->
      <p v-if="error" role="alert" class="alert-danger">{{ error }}</p>
      <p v-else-if="matches.length=== 0 && isSuccess" role="alert" class="alert">Keine ICF-Codes gefunden. Bitte
        versuche es mit einer anderen Formulierung oder einem anderen Satz.</p>
    </article>

    <!-- Ergebnis -->
    <article v-if="matches.length">
      <header>
        <h2>So sieht das System deinen Satz</h2>
        <p>
          <small>
            ⚠ KI-gestützte Einordnung – kein Ersatz für ärztliche oder
            therapeutische Diagnose. Fehler sind möglich.
          </small>
        </p>
        <p>Bitte bewerte das Analyse-Ergebnis (Click auf die unterstrichenen Wörter oder markiere
          verpasste Textstellen)! Die Eingaben werden
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

    <div v-if="isSuccess" style="margin-bottom: 20px">
      <h3>Danke für dein Feedback!</h3>
      <a href="#article-input" @click="handleReset">Möchtest du noch eine Erfahrung beschreiben?</a>
    </div>

    <h3>Weitere Informationen</h3>
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
      <button @click="shareApp"><img src="/share-button-green.svg" style="margin-right: 10px; height:24px;"/> Diese App
        teilen
      </button>
    </details>

    <!-- Verlauf – Pico stylt <details>/<summary> als Accordion automatisch -->
    <details v-if="history.length">
      <summary>Deine bisherigen Eingaben (nur lokal gespeichert): {{ history.length }} Einträge</summary>
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

      <div class="footer-content"
           style="display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:20px;">

        <article style="text-align: center;">
          <p>Du bist nicht allein.</p>
          <p style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">
            {{ usageStats?.overview.unique_sessions.toLocaleString('de-DE') }} Menschen
          </p>
          <p>haben ihre Erfahrung bereits geteilt.</p>
          <p>
            <small>Danke, dass du mitmachst!</small>
          </p>
        </article>

        <div style="display:flex; gap:10px; align-items:center;">
          <img src="/heart-icon.svg" style="height:48px;" alt="Heart Icon"/>
          <span>Wenn dir die App gefällt, teile sie gerne mit deinen Freunden!</span>
        </div>
        <button @click="shareApp"><img src="/share-button-green.svg" style="margin-right: 10px; height:24px;"/> Diese
          App teilen
        </button>
        <Turnstile
            :key="turnstileKey"
            :site-key="TURNSTILE_SITE_KEY"
            v-model="turnstileToken"
        />
      </div>
    </footer>
  </main>
</template>

<script setup lang="ts">
import {onMounted, ref, watch} from 'vue'
import IcfResult from '~/components/IcfResult.vue'
import {useIcfAnalyzer} from '~/composables/useIcfAnalyzer'
import {Turnstile} from '@sctg/turnstile-vue3';
import type {StatsResponse} from '~/types/stats'


const TURNSTILE_SITE_KEY = import.meta.env.VITE_TURNSTILE_SITE_KEY as string
const turnstileKey = ref(0)

const dev_mode = import.meta.env.DEV

const {
  sentence, sentenceUuid, matches, isLoading, isSuccess, error,
  history, uniqueCodes, annotatedHtml, dynamicCss, groupedMatches,
  get_example_sentence, get_example_error, analyze, reset
} = useIcfAnalyzer()

const turnstileToken = ref('')
const usageStats = ref<StatsResponse | null>(null)
const fakeData = ref(false)

async function handleSubmit() {
  if (!turnstileToken.value || !sentence.value.trim() || isLoading.value || isSuccess.value) return
  await analyze(turnstileToken.value, fakeData.value)
  turnstileToken.value = ''
  turnstileKey.value++;
}

const linkText = ref('Link in die Zwischenablage kopieren')

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
  fakeData.value = false
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

onMounted(() => {
  const us = fetch('/api/stats_read.php')
      .then(res => res.json())
      .then(data => {
        usageStats.value = data
      })
      .catch(err => {
        console.error('Fehler beim Abrufen der Nutzungsstatistiken:', err)
      })
})

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
