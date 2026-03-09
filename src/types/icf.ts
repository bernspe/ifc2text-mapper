// src/types/icf.ts

import { z } from 'zod'

// ── Zod-Schemas (gleiche Schemas wie serverseitig → single source of truth) ───

export const MatchSchema = z.object({
  textstelle:   z.string(),
  code:         z.string(),
  beschreibung: z.string(),
})

export const AnalyzeResponseSchema = z.object({
  matches: z.array(MatchSchema),
})

export const AnalyzeRequestSchema = z.object({
  sentence:       z.string().min(3).max(2000),
  turnstileToken: z.string().min(1),
})

export type Match           = z.infer<typeof MatchSchema>
export type AnalyzeResponse = z.infer<typeof AnalyzeResponseSchema>
export type AnalyzeRequest  = z.infer<typeof AnalyzeRequestSchema>

// ── Farbpalette (zentral definiert, von Composable + Component genutzt) ───────
export const PALETTE: ReadonlyArray<readonly [string, string]> = [
  ['#fff176', '#f9a825'],  // Gelb
  ['#80deea', '#00838f'],  // Cyan
  ['#a5d6a7', '#2e7d32'],  // Grün
  ['#ce93d8', '#6a1b9a'],  // Lila
  ['#ffab91', '#bf360c'],  // Orange
  ['#90caf9', '#0d47a1'],  // Blau
  ['#f48fb1', '#880e4f'],  // Pink
  ['#bcaaa4', '#4e342e'],  // Braun
] as const

export function paletteFor(code: string, uniqueCodes: string[]): readonly [string, string] {
  const idx = uniqueCodes.indexOf(code)
  return PALETTE[idx % PALETTE.length]!
}
