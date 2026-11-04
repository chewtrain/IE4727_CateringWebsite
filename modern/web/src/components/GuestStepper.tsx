import { useEffect, useState } from 'react'
import { MinusIcon, PlusIcon } from './Icons'

/** Number of guests with − / + buttons; typing is allowed and clamped on blur. */
export function GuestStepper({
  value,
  min,
  max,
  onChange,
  id,
  label = 'Number of guests',
  step = 5,
  compact = false,
}: {
  value: number
  min: number
  max: number
  onChange: (guests: number) => void
  id: string
  label?: string
  step?: number
  compact?: boolean
}) {
  const [text, setText] = useState(String(value))
  useEffect(() => setText(String(value)), [value])

  const clamp = (n: number) => Math.min(max, Math.max(min, Math.round(n)))
  const commit = (raw: string) => {
    const n = Number(raw)
    const next = Number.isFinite(n) && raw.trim() !== '' ? clamp(n) : value
    setText(String(next))
    if (next !== value) onChange(next)
  }
  const size = compact ? 'h-8 w-8' : 'h-10 w-10'

  return (
    <div className="flex items-center gap-2">
      <label htmlFor={id} className="sr-only">
        {label}
      </label>
      <button
        type="button"
        className={`${size} grid place-items-center rounded-full border border-line text-ink transition hover:border-ink disabled:opacity-30`}
        onClick={() => onChange(clamp(value - step))}
        disabled={value <= min}
        aria-label={`${step} fewer guests`}
      >
        <MinusIcon width={16} height={16} />
      </button>
      <input
        id={id}
        type="number"
        inputMode="numeric"
        min={min}
        max={max}
        step={1}
        value={text}
        onChange={(e) => {
          setText(e.target.value)
          const n = Number(e.target.value)
          if (Number.isInteger(n) && n >= min && n <= max) onChange(n)
        }}
        onBlur={(e) => commit(e.target.value)}
        className={`${compact ? 'w-14 py-1 text-[15px]' : 'w-20 py-2 text-lg'} rounded-lg border border-transparent bg-mist text-center font-semibold tabular-nums focus:border-accent focus:bg-white focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none`}
      />
      <button
        type="button"
        className={`${size} grid place-items-center rounded-full border border-line text-ink transition hover:border-ink disabled:opacity-30`}
        onClick={() => onChange(clamp(value + step))}
        disabled={value >= max}
        aria-label={`${step} more guests`}
      >
        <PlusIcon width={16} height={16} />
      </button>
    </div>
  )
}
