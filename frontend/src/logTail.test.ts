import { describe, expect, it } from 'vitest'
import type { LogLine } from './api'
import { plainText, TailBuffer } from './logTail'

const line = (id: number, message = `line ${id}`): LogLine => ({
  id,
  timestamp: '2026-10-11T12:00:00.000+00:00',
  receivedAt: '2026-10-11T12:00:00.000+00:00',
  level: 'info',
  message,
  context: [],
  sourceId: 's1',
  sourceName: 'API',
})

describe('TailBuffer', () => {
  it('drops lines it already has and reports only new ones', () => {
    const buffer = new TailBuffer()

    expect(buffer.add([line(1), line(2)])).toBe(2)
    // The server repeats the last seconds on purpose.
    expect(buffer.add([line(2), line(3)])).toBe(1)
    expect(buffer.lines().map((l) => l.id)).toEqual([1, 2, 3])
  })

  it('slots a late commit in by id', () => {
    const buffer = new TailBuffer()
    buffer.add([line(10), line(12)])

    buffer.add([line(11)])

    expect(buffer.lines().map((l) => l.id)).toEqual([10, 11, 12])
  })

  it('keeps only the newest lines', () => {
    const buffer = new TailBuffer(3)

    buffer.add([line(1), line(2), line(3), line(4), line(5)])

    expect(buffer.lines().map((l) => l.id)).toEqual([3, 4, 5])
  })

  it('starts over when cleared', () => {
    const buffer = new TailBuffer()
    buffer.add([line(1)])

    buffer.clear()

    expect(buffer.lines()).toEqual([])
    expect(buffer.add([line(1)])).toBe(1)
  })
})

describe('plainText', () => {
  it('removes terminal colour codes and control characters but keeps tabs and newlines', () => {
    expect(plainText('\x1b[31mERROR\x1b[0m: disk\x07 full\tnow\nnext')).toBe('ERROR: disk full\tnow\nnext')
  })

  it('leaves normal text, HTML-looking text included, untouched', () => {
    expect(plainText('<script>alert(1)</script> zażółć')).toBe('<script>alert(1)</script> zażółć')
  })
})
