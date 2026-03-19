import {ref, computed} from 'vue'
import {useStorage, useThrottleFn} from '@vueuse/core'
import {
    AnalyzeResponseSchema,
    PALETTE,
    type Match,
} from '~/types/icf'

import _example_responses from '~/assets/example-response.json'

const example_responses = _example_responses as Array<{
    sentence: string,
    matches: Array<{
        code: string,
        textstelle: string,
        beschreibung: string
    }>
}>
// get a random choice from the example responses



// ── Composable ────────────────────────────────────────────────────────────────
export function useIcfAnalyzer() {
    const sentence = ref('')
    const sentenceUuid = ref('')
    const matches = ref<Match[]>([])
    const isLoading = ref(false)
    const isSuccess = ref(false)
    const error = ref<string | null>(null)

    const example_response = ref(example_responses[Math.floor(Math.random() * example_responses.length)])

    // Verlauf persistent im localStorage (VueUse, eine Zeile)
    const history = useStorage<string[]>('icf-history', [])

    const reset = () => {
        sentence.value = ''
        sentenceUuid.value = ''
        matches.value = []
        isLoading.value = false
        isSuccess.value = false
        error.value = null
    }

    const get_example_sentence = () => {
        example_response.value = example_responses[Math.floor(Math.random() * example_responses.length)]
        return example_response.value.sentence
    }

    const get_example_error = () => {
        try {
            throw new Error('Dies ist ein Beispiel-Fehler, um die Fehleranzeige zu testen.')
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unbekannter Fehler'
        }
    }
    // ── Analyse ────────────────────────────────────────────────────────────────
    // useThrottleFn verhindert Doppelklicks / schnelles Absenden
    const analyze = useThrottleFn(async (turnstileToken: string, language: string, fake: boolean) => {
        const trimmed = sentence.value.trim()
        if (!trimmed || isLoading.value) return

        isLoading.value = true
        error.value = null

        const t0 = performance.now()

        try {
            sentenceUuid.value = crypto.randomUUID()
            if (!fake) {
                const res = await fetch('/api/analyze.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({sentence: trimmed, turnstileToken, lang:language}),
                })

                if (!res.ok) {
                    const msg = await res.text()
                    throw new Error(`Server ${res.status}: ${msg}`)
                }
                const raw = await res.json()
                // Zod validiert die Antwort – wirft wenn das Format nicht stimmt

                const data = AnalyzeResponseSchema.parse(raw)
                matches.value = data.matches
            } else {
                // Fake-Daten für Tests
                matches.value = example_response.value['matches']
            }


            // History: Duplikate raus, max 10
            history.value = [trimmed, ...history.value.filter(h => h !== trimmed)].slice(0, 10)
            isSuccess.value = true

            // ── Stats: Erfolg ───────────────────────────────────────────────────────
    sendStats({
      sentence_uuid: sentenceUuid.value,
      sentence:      trimmed,
      success:       true,
      error_msg:     '',
      match_count:   matches.value.length,
      duration_ms:   Math.round(performance.now() - t0),
    }).catch(e => console.error('[Stats]', e))
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unbekannter Fehler'
            matches.value = []

            // ── Stats: Fehler ───────────────────────────────────────────────────────
    sendStats({
      sentence_uuid: sentenceUuid.value,
      sentence:      trimmed,
      success:       false,
      error_msg:     error.value,
      match_count:   0,
      duration_ms:   Math.round(performance.now() - t0),
    }).catch(e => console.error('[Stats]', e))
        } finally {
            isLoading.value = false
        }
    }, 500)

    // ── Computed: eindeutige Codes in Reihenfolge des Auftretens ──────────────
    const uniqueCodes = computed(() => [...new Set(matches.value.map(m => m.code))])

    // ── Computed: annotiertes HTML ────────────────────────────────────────────
    const annotatedHtml = computed(() =>
        buildAnnotatedHtml(sentence.value, matches.value, uniqueCodes.value)
    )

    // ── Computed: dynamisches CSS für Mark-Farben ─────────────────────────────
    const dynamicCss = computed(() =>
        uniqueCodes.value.map((code, idx) => {
            const [bg, accent] = PALETTE[idx % PALETTE.length]!
            return `
        .icf-mark-${code} {
          background: ${bg};
          border-bottom: 2px solid ${accent};
          border-radius: 3px;
          padding: 1px 4px;
          font-weight: 600;
          cursor: default;
          position: relative;
          transition: filter .15s;
          color: #1a1a1a;
        }
        .icf-mark-${code}:hover { filter: brightness(.9); }
        .icf-mark-${code} .icf-tooltip { border-left: 3px solid ${accent}; }
        .icf-mark-${code} .icf-tooltip-code { color: ${accent}; }
      `
        }).join('\n')
    )

    // ── Computed: Matches nach Code gruppiert (für Legende) ───────────────────
    const groupedMatches = computed(() => {
        const groups = new Map<string, string[]>()
        for (const m of matches.value) {
            if (!groups.has(m.code)) groups.set(m.code, [])
            groups.get(m.code)!.push(m.beschreibung)
        }
        return groups
    })


    return {
        sentence,
        sentenceUuid,
        matches,
        isLoading,
        isSuccess,
        error,
        history,
        uniqueCodes,
        annotatedHtml,
        dynamicCss,
        groupedMatches,
        get_example_sentence,
        get_example_error,
        analyze,
        reset
    }
}

// ── HTML-Aufbau ───────────────────────────────────────────────────────────────

type Span = {
    start: number
    end: number
    phrase: string
    spanMatches: Match[]
}

function buildAnnotatedHtml(sentence: string, matches: Match[], uniqueCodes: string[]): string {
    if (!matches.length || !sentence) return escHtml(sentence)

    const spans = resolveSpans(sentence, matches)
    const parts: string[] = []
    let cursor = 0

    for (const {start, end, phrase, spanMatches} of spans) {
        if (cursor < start) parts.push(escHtml(sentence.slice(cursor, start)))

        const primaryCode = spanMatches[0]?.code ?? ''
        const dataCodes = spanMatches.map(m => m.code).join(' ')
        const dataDescs = spanMatches.map(m => m.beschreibung).join(' | ')

        const tooltipRows = spanMatches.map((m) => {
            const idx = uniqueCodes.indexOf(m.code)
            const [, accent] = PALETTE[idx % PALETTE.length]!
            return `<div class="icf-tooltip-row">
        <span class="icf-tooltip-code" style="color:${accent}">${escHtml(m.code)}</span>
        <span class="icf-tooltip-desc">${escHtml(m.beschreibung)}</span>
        <span>Ist das korrekt?</span>
        <div role="group">
        <button class="tooltip-feedback-button" data-feedback="up" data-code="${escHtml(m.code)}" data-textstelle="${escHtml(phrase)}">👍</button>
        <button class="tooltip-feedback-button" data-feedback="down" data-code="${escHtml(m.code)}" data-textstelle="${escHtml(phrase)}">👎</button>
        </div>
      </div>`
        }).join('')

        parts.push(
            `<mark class="icf-mark-${primaryCode}" data-action="tooltip-toggle"` +
            ` data-code="${escHtml(dataCodes)}"` +
            ` data-textstelle="${phrase}"` +
            ` data-beschreibung="${escHtml(dataDescs)}">` +
            escHtml(phrase) +
            `<span class="icf-tooltip">${tooltipRows}</span>` +
            `</mark>`
        )
        cursor = end
    }

    if (cursor < sentence.length) parts.push(escHtml(sentence.slice(cursor)))
    return parts.join('')
}
function resolveSpans(sentence: string, matches: Match[]): Span[] {
  const spans: Span[] = []

  // Längste Phrase zuerst → verhindert dass kurze Phrases längere verdrängen
  const sorted = [...matches].sort((a, b) => b.textstelle.length - a.textstelle.length)

  for (const match of sorted) {
    const phrase = match.textstelle.trim()
    if (!phrase) continue

    const found = findPhrase(phrase, sentence)
    if (!found) continue

    // ── Prüfen ob der gefundene Bereich einen bestehenden Span überlappt ────
    const overlapping = spans.find(s =>
      found.start < s.end && found.end > s.start
    )

    if (overlapping) {
      // Match dem überlappenden Span hinzufügen statt ihn zu verwerfen –
      // beide Codes werden dann im selben <mark> angezeigt
      overlapping.spanMatches.push(match)
      continue
    }

    // ── Neuer Span ────────────────────────────────────────────────────────────
    spans.push({
      start:       found.start,
      end:         found.end,
      // Tatsächlichen Substring aus dem Satz nehmen, nicht die KI-Phrase –
      // damit stimmt der markierte Text immer mit dem Original überein
      phrase:      sentence.slice(found.start, found.end),
      spanMatches: [match],
    })
  }

  return spans.sort((a, b) => a.start - b.start)
}

function findPhrase(phrase: string, sentence: string): { start: number, end: number } | null {
  // ── 1. Exakter Match ───────────────────────────────────────────────────────
  const exact = sentence.indexOf(phrase)
  if (exact !== -1) return { start: exact, end: exact + phrase.length }

  // ── 2. Case-insensitiver Match ─────────────────────────────────────────────
  const sentenceLower = sentence.toLowerCase()
  const phraseLower   = phrase.toLowerCase()
  const casePos       = sentenceLower.indexOf(phraseLower)
  if (casePos !== -1) return { start: casePos, end: casePos + phrase.length }

  // ── 3. Fuzzy: erstes + letztes Wort als Anker ──────────────────────────────
  // Findet "rechte Arm schmerzt" auch wenn im Satz "rechte Arm einschläft und schmerzt" steht
  const words = phrase.split(/\s+/).filter(Boolean)
  if (words.length < 2) return null

  const firstWord = words[0]!.toLowerCase()
  const lastWord  = words[words.length - 1]!.toLowerCase()

  const startPos = sentenceLower.indexOf(firstWord)
  if (startPos === -1) return null

  // Letztes Wort nach dem ersten suchen, aber nicht zu weit weg
  // (max. 3× die Länge der Originalphrase – verhindert wilde Ferntreffer)
  const searchLimit = startPos + phrase.length * 3
  const lastPos     = sentenceLower.indexOf(lastWord, startPos + firstWord.length)

  if (lastPos === -1 || lastPos > searchLimit) return null

  return { start: startPos, end: lastPos + lastWord.length }
}

function escHtml(s: string): string {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

// Neue Hilfsfunktion – außerhalb des Composables
async function sendStats(payload: {
  sentence_uuid: string
  sentence:      string
  success:       boolean
  error_msg:     string
  match_count:   number
  duration_ms:   number
}): Promise<void> {
  await fetch('/api/stats.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  })
}
