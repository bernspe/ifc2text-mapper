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
const example_response = example_responses[Math.floor(Math.random() * example_responses.length)]


// ── Composable ────────────────────────────────────────────────────────────────
export function useIcfAnalyzer() {
    const sentence = ref('')
    const sentenceUuid = ref('')
    const matches = ref<Match[]>([])
    const isLoading = ref(false)
    const isSuccess = ref(false)
    const error = ref<string | null>(null)

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
        console.log('Using example sentence:', example_response.sentence)
        sentence.value = example_response.sentence
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
    const analyze = useThrottleFn(async (turnstileToken: string, fake: boolean) => {
        const trimmed = sentence.value.trim()
        if (!trimmed || isLoading.value) return

        isLoading.value = true
        error.value = null

        try {
            sentenceUuid.value = crypto.randomUUID()
            if (!fake) {
                const res = await fetch('/api/analyze.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({sentence: trimmed, turnstileToken}),
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
                matches.value = example_response['matches']
            }


            // History: Duplikate raus, max 10
            history.value = [trimmed, ...history.value.filter(h => h !== trimmed)].slice(0, 10)
            isSuccess.value = true
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unbekannter Fehler'
            matches.value = []
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
        const dataCodes = spanMatches.map(m => m.code).join(', ')
        const dataDescs = spanMatches.map(m => m.beschreibung).join(' | ')

        const tooltipRows = spanMatches.map((m) => {
            const idx = uniqueCodes.indexOf(m.code)
            const [, accent] = PALETTE[idx % PALETTE.length]!
            return `<div class="icf-tooltip-row">
        <span class="icf-tooltip-code" style="color:${accent}">${escHtml(m.code)}</span>
        <span class="icf-tooltip-desc">${escHtml(m.beschreibung)}</span>
        <span>Ist das korrekt?</span>
        <div role="group">
        <button class="tooltip-feedback-button" data-feedback="up" data-code="${m.code}" data-textstelle="${phrase}">👍</button>
        <button class="tooltip-feedback-button" data-feedback="down" data-code="${m.code}" data-textstelle="${phrase}">👎</button>
        </div>
      </div>`
        }).join('')

        parts.push(
            `<mark class="icf-mark-${primaryCode}" data-action="tooltip-toggle"` +
            ` data-code="${escHtml(dataCodes)}"` +
            ` data-textstelle="${phrase}"`+
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
    const occupied: Set<number> = new Set()

    // Längste Phrase zuerst → verhindert Teilüberschreibungen
    const sorted = [...matches].sort((a, b) => b.textstelle.length - a.textstelle.length)

    for (const match of sorted) {
        const phrase = match.textstelle.trim()
        if (!phrase) continue
        const pos = sentence.indexOf(phrase)
        if (pos === -1) continue
        if ([...Array(phrase.length)].some((_, i) => occupied.has(pos + i))) continue
        for (let i = pos; i < pos + phrase.length; i++) occupied.add(i)

        const existing = spans.find(s => s.start === pos && s.end === pos + phrase.length)
        existing
            ? existing.spanMatches.push(match)
            : spans.push({start: pos, end: pos + phrase.length, phrase, spanMatches: [match]})
    }

    return spans.sort((a, b) => a.start - b.start)
}

function escHtml(s: string): string {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}
