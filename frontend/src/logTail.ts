import type { LogLine } from './api'

export const TAIL_BUFFER = 2000

/**
 * Lines of a live tail in arrival order, newest last. The server repeats recent lines on purpose
 * (rows committed late can carry a lower id), so lines are kept by id and duplicates dropped.
 * Only the newest TAIL_BUFFER lines stay in memory.
 */
export class TailBuffer {
  private readonly byId = new Map<number, LogLine>()

  constructor(private readonly capacity = TAIL_BUFFER) {}

  /** Adds lines and returns how many were new. */
  add(lines: LogLine[]): number {
    let added = 0
    for (const line of lines) {
      if (this.byId.has(line.id)) continue
      this.byId.set(line.id, line)
      added++
    }
    if (added === 0) return 0

    if (this.byId.size > this.capacity) {
      const ids = [...this.byId.keys()].sort((a, b) => a - b)
      for (const id of ids.slice(0, ids.length - this.capacity)) this.byId.delete(id)
    }
    return added
  }

  /** Arrival order: ids grow with arrival, late commits slot in by their id. */
  lines(): LogLine[] {
    return [...this.byId.values()].sort((a, b) => a.id - b.id)
  }

  clear(): void {
    this.byId.clear()
  }
}

// ANSI escape sequences (colours, cursor moves) and other control characters, keeping tab and newline.
const CONTROL = /\x1b\[[0-?]*[ -/]*[@-~]|\x1b[@-Z\\-_]|[\x00-\x08\x0b-\x1f\x7f]/g

/** Log text comes from clients: shown as plain text, minus terminal control codes. */
export function plainText(text: string): string {
  return text.replace(CONTROL, '')
}
