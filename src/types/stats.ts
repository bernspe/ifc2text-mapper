// src/types/stats.ts

export interface StatsOverview {
  total_requests:   number
  unique_sessions:  number
  total_success:    number
  total_errors:     number
  success_rate_pct: number
  avg_duration_ms:  number
  min_duration_ms:  number
  max_duration_ms:  number
  avg_match_count:  number
  first_request:    string | null
  last_request:     string | null
}

export interface StatsDailyEntry {
  date:            string
  requests:        number
  unique_sessions: number
  success:         number
  errors:          number
  avg_duration_ms: number
  avg_match_count: number
}

export interface StatsHourlyEntry {
  hour:     number   // 0–23
  requests: number
}

export interface StatsErrorEntry {
  error_msg: string
  count:     number
  last_seen: string
}

export interface StatsResponse {
  overview: StatsOverview
  daily:    StatsDailyEntry[]
  hourly:   StatsHourlyEntry[]
  errors:   StatsErrorEntry[]
}