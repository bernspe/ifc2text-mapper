
<template>
  <main class="container">
    <header>
              <h1>
                <img src="/accessibility-logo.svg" style="max-height: 60px" alt="Accessibility Logo"/>
                ICF Code Mapper</h1>
              <h5>Internationale Klassifikation der Funktionsfähigkeit</h5>
      <p class="small">Dies ist ein Demo-Tool zur Analyse von Texten zu Teilhabestörungen. Nicht für diagnostische Zwecke geeignet. Achtung: KI kann Fehler machen.</p>
    </header>

    <!-- Eingabe -->
    <article>
      <header>
        <h2>Text-Eingabe</h2>
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

      <label>
    <input name="fake" type="checkbox" role="switch" v-model="fakeData"/>
    Fake Data
  </label>


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
    </article>

    <!-- Ergebnis -->
    <article v-if="matches.length">
      <header>
        <h2>Analyse-Ergebnis</h2>
        <p class="small">Bitte bewerte das Analyse-Ergebnis (Click auf die unterstrichenen Wörter)! Die Eingaben werden zu Forschungszwecken anonym gespeichert.</p>
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
      </ul>
    </details>

    <details>
      <summary>Referenzen / Datenschutz / Impressum / Kontakt / Cookies</summary>
      <ul>
        <li>Verwendete LLM über die API von <a href="https://llm.scads.ai/docs/" target="_blank" >SCADS.AI</a>. Es erfolgt kein Training der KI mit den eingegebenen Daten.</li>
        <li>ICF Icons sind von Tai Takahashi <a href="http://www.icfillustration.com/icfil_eng/top.html">Link zu den Illustrationen</a></li>
        <li><a href="https://renecol.org/datenschutzerklaerung/">Datenschutz </a></li>
        <li><a href="https://renecol.org/impressum/">Impressum</a></li>
        <li>Kontakt: Follow me on <a href="https://www.linkedin.com/in/peter-bernstein-renecol/">LinkedIn</a>, EMail: <a href="mailto:post@renecol.org">post@renecol.org</a></li>
        <li>Diese App verwendet keine Cookies. Lediglich der Verlauf wird lokal im Browser gespeichert.</li>
      </ul>
    </details>

    <footer>
      <div>
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
import {ref, watch} from 'vue'
import IcfResult from '~/components/IcfResult.vue'
import {useIcfAnalyzer} from '~/composables/useIcfAnalyzer'
import {Turnstile} from '@sctg/turnstile-vue3';



const TURNSTILE_SITE_KEY = import.meta.env.VITE_TURNSTILE_SITE_KEY as string
const turnstileKey = ref(0)
const {
  sentence, sentenceUuid, matches, isLoading, isSuccess, error,
  history, uniqueCodes, annotatedHtml, dynamicCss, groupedMatches,
  get_example_sentence, get_example_error,analyze, reset
} = useIcfAnalyzer()

const turnstileToken = ref('')
const fakeData = ref(false)
async function handleSubmit() {
  if (!turnstileToken.value || !sentence.value.trim() || isLoading.value || isSuccess.value) return
  await analyze(turnstileToken.value,fakeData.value)
  turnstileToken.value = ''
  turnstileKey.value++;
}


watch(fakeData, (newVal:boolean) => {
  if (newVal) {
    get_example_sentence()
  } else {
    sentence.value = ''
    reset()
  }
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


.alert-danger {
  background-color: #feebee;
  color: #b71c1c;
}

button {
  margin-right: 0.5rem;
  margin-top: 0.5rem;
}

</style>
