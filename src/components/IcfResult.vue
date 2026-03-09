<!-- src/components/IcfResult.vue -->
<!-- Pico classless stylt <article>, <header>, <ul>, <mark> automatisch -->
<template>
  <Transition name="slide-up">
    <section v-if="matches.length">

      <!-- Dynamisches CSS für ICF-Farben + Tooltip -->
      <component :is="'style'" v-html="dynamicCss + TOOLTIP_CSS"/>

      <hgroup>
        <h3>Markierter Satz</h3>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <!-- IcfResult.vue -->
        <p class="annotated" v-html="annotatedHtml" @click.capture="onFeedbackClick" ref="containerRef"/>
      </hgroup>

      <h3>Gefundene ICF-Codes</h3>
      <ul>
        <li v-for="[code, descs] in groupedMatches" :key="code">
          <span
              class="swatch"
              :style="{
              background:  paletteFor(code, uniqueCodes)[0],
              borderColor: paletteFor(code, uniqueCodes)[1],
            }"
          />
          <div>
            <strong :style="{ color: paletteFor(code, uniqueCodes)[1], marginRight: '6px' }">
              {{ code }}
            </strong>
            <span> {{ _icfcodes[code].t }}</span>

            <ul>
              <li v-for="(d, i) in descs" :key="i">{{ d }}</li>
            </ul>
          </div>

          <div>
            <img
                :src="imageServer()+`icf-pics/${code}.jpg`"
                :alt="code"
                style="max-height: 80px; width:auto; object-fit: contain; min-width:10em;"
            />
          </div>

        </li>
      </ul>

    </section>
  </Transition>
</template>

<script setup lang="ts">
import {paletteFor} from '~/types/icf'
import type {Match} from '~/types/icf'
import __icfcodes from "../assets/icf_codes3.json";
import {ref} from "vue";
import {onClickOutside} from "@vueuse/core";

interface ICFItemStructure extends Object {
  'a': string,
  'b': string,
  'c': Array<string>
  'm': string,
  'h': string,
  't': string,
  'p': string
}

const containerRef = ref<HTMLElement | null>(null)

const _icfcodes: Record<string, ICFItemStructure> = __icfcodes;

const props = defineProps<{
  sentenceUuid: string
  sentence: string
  matches: Match[]
  uniqueCodes: string[]
  annotatedHtml: string
  dynamicCss: string
  groupedMatches: Map<string, string[]>
}>()

function onFeedbackClick(e: MouseEvent) {
  console.log('Clicked element:', e.target)
  const mark = (e.target as HTMLElement).closest('[data-action="tooltip-toggle"]') as HTMLElement | null
  const btn = (e.target as HTMLElement).closest('[data-feedback]') as HTMLElement | null
  if (btn) {
    e.preventDefault()
    const code = btn.dataset.code!
    const textstelle = btn.dataset.textstelle!
    const correct = btn.dataset.feedback === 'up'
    setFeedback(code, textstelle, correct)
    e.stopPropagation()
  }
  if (mark) {
    document.querySelectorAll('mark.icf-open').forEach(m => {
      if (m !== mark) m.classList.remove('icf-open')
    })
    mark.classList.toggle('icf-open')
    e.stopPropagation()
  }
}

async function sendFeedback(
  code:         string,
  textstelle:   string,
  correct:      boolean
) {
  await fetch('/api/feedback.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      sentence_uuid: props.sentenceUuid,  // UUIDv4, beim ersten Analyze generiert
      sentence:      props.sentence,
      code,
      textstelle,
      correct,
    }),
  })
}

function setFeedback(code: string, textstelle: string, correct: boolean) {

   // Mark-Element im DOM finden und Badge-Klasse setzen
  const mark = document.querySelector(
    `mark[data-code="${code}"][data-textstelle="${textstelle}"]`
  ) as HTMLElement | null

  if (mark) {
    mark.classList.remove('icf-feedback-up', 'icf-feedback-down')
    mark.classList.add(correct ? 'icf-feedback-up' : 'icf-feedback-down')
  }

  // Fire-and-forget – Fehler loggen aber UI nicht blockieren
  sendFeedback(code, textstelle, correct).catch(err =>
    console.error('[Feedback]', err)
  )
}

onClickOutside(containerRef, () => {
  document.querySelectorAll('mark.icf-open')
      .forEach(m => m.classList.remove('icf-open'))
})
const imageServer = () => import.meta.env.VITE_IMAGE_SERVER

const TOOLTIP_CSS = `
  mark { position: relative; display: inline; padding: 1px 4px; border-radius: 3px; font-weight: 600; cursor: default; transition: filter .15s; }
  mark:hover { filter: brightness(.9); }

  .icf-tooltip {
    visibility: hidden; opacity: 0; pointer-events: none;
    position: absolute; bottom: calc(100% + 8px); left: 50%;
    cursor: pointer;
    transform: translateX(-50%) translateY(4px);
    background: var(--pico-card-background-color);
    border: 1px solid var(--pico-muted-border-color);
    border-radius: var(--pico-border-radius);
    padding: .6rem .9rem; min-width: 270px; max-width: 380px;
    box-shadow: 0 8px 24px rgba(0,0,0,.1); z-index: 200;
    transition: opacity .2s, visibility .2s, transform .2s;
    font-size: .8rem; font-weight: 400; white-space: normal; line-height: 1.5;
  }
  .icf-tooltip [role="group"] {
  --pico-font-size: .8rem;
  margin-top: .5rem;
}

  .icf-tooltip::after {
    content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
    border: 6px solid transparent; border-top-color: var(--pico-muted-border-color);
  }

mark.icf-open .icf-tooltip {
  visibility: visible; opacity: 1;
  pointer-events: auto;
  transform: translateX(-50%) translateY(0);
}
  .icf-tooltip-row { display: grid; grid-template-columns: 3rem 1fr; gap: .25rem .6rem; padding: .25rem 0; }
  .icf-tooltip-row + .icf-tooltip-row { border-top: 1px solid var(--pico-muted-border-color); padding-top: .4rem; }
  .icf-tooltip-code { font-weight: 700; font-family: monospace; }
  .icf-tooltip-desc { color: var(--pico-muted-color); }

  mark.icf-feedback-up::after {
  background: #2e7d32;
  color: white;
  border-radius: 50%;
  padding: 2px;
  content: '👍';
  font-size: 1 rem;
  position: absolute;
  top: -8px;
  right: -4px;
  line-height: 1;
}

mark.icf-feedback-down::after {
background: darkred;
  color: white;
  border-radius: 50%;
  padding: 2px;
  content: '👎';
  font-size: 1 rem;
  position: absolute;
  top: -8px;
  right: -4px;
  line-height: 1;
}
`
</script>

<style scoped>
/* Pico stylt <ul>, <li>, <strong>, <section> bereits – nur Deltas nötig */
.annotated {
  font-size: 1.3rem;
  line-height: 2.2;
}

.swatch {
  display: inline-block;
  width: 11px;
  height: 11px;
  border-radius: 50%;
  border: 2px solid;
  flex-shrink: 0;
  margin-top: 4px;
}

/* Legende: zweispaltig (Punkt + Text) */
ul > li {
  display: flex;
  gap: .75rem;
  align-items: flex-start;
}

/* Verschachtelte Beschreibungsliste ohne Bullet-Override */
ul > li ul {
  margin-top: .2rem;
  padding-left: 0;
}

ul > li ul li {
  display: list-item;
  list-style: none;
}

ul > li ul li::before {
  content: "↳ ";
  color: var(--pico-muted-color);
}

/* Transition */
.slide-up-enter-active {
  transition: opacity .3s ease, transform .3s ease;
}

.slide-up-leave-active {
  transition: opacity .2s ease;
}

.slide-up-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.slide-up-leave-to {
  opacity: 0;
}
</style>
