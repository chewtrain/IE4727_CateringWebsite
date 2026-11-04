import { useEffect, type ReactNode } from 'react'
import { motion, useSpring, useTransform } from 'motion/react'
import { money } from '../lib/format'

// Small animation building blocks shared by the pages. MotionConfig in App.tsx
// turns movement off for visitors who ask their system to reduce motion.

export const easeApple = [0.2, 0.8, 0.2, 1] as const

/** Fades and lifts its children into place the first time they scroll into view. */
export function Reveal({
  children,
  delay = 0,
  className,
  as = 'div',
}: {
  children: ReactNode
  delay?: number
  className?: string
  as?: 'div' | 'section' | 'li'
}) {
  const Tag = motion[as]
  return (
    <Tag
      className={className}
      initial={{ opacity: 0, y: 28 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '0px 0px -80px 0px' }}
      transition={{ duration: 0.8, ease: easeApple, delay }}
    >
      {children}
    </Tag>
  )
}

/** Entrance animation for the top of a page. */
export function PageIntro({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <motion.div
      className={className}
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.6, ease: easeApple }}
    >
      {children}
    </motion.div>
  )
}

/** A price that glides to its new value instead of jumping. */
export function AnimatedMoney({ value, className }: { value: number; className?: string }) {
  const spring = useSpring(value, { stiffness: 140, damping: 22, mass: 0.6 })
  const text = useTransform(spring, (v) => money(Math.round(v * 100) / 100))
  useEffect(() => {
    spring.set(value)
  }, [spring, value])
  return (
    <span className={className}>
      <motion.span aria-hidden="true">{text}</motion.span>
      <span className="sr-only">{money(value)}</span>
    </span>
  )
}

/**
 * Slow, blurred colour fields behind the hero. Purely decorative, so it is
 * hidden from assistive technology and ignores the pointer.
 */
export function AmbientBackground({ className = '' }: { className?: string }) {
  const blobs = [
    { color: 'bg-orange-300/50', size: 'h-[34rem] w-[34rem]', pos: '-left-40 -top-48', x: [0, 60, -30, 0], y: [0, 40, 70, 0], d: 22 },
    { color: 'bg-amber-200/60', size: 'h-[28rem] w-[28rem]', pos: 'right-[-8rem] top-10', x: [0, -50, 20, 0], y: [0, 60, -20, 0], d: 26 },
    { color: 'bg-rose-200/50', size: 'h-[24rem] w-[24rem]', pos: 'left-1/3 top-56', x: [0, 40, -40, 0], y: [0, -30, 30, 0], d: 30 },
  ]
  return (
    <div aria-hidden="true" className={`pointer-events-none absolute inset-0 -z-10 overflow-hidden ${className}`}>
      {blobs.map((b, i) => (
        <motion.div
          key={i}
          className={`absolute rounded-full blur-3xl ${b.color} ${b.size} ${b.pos}`}
          animate={{ x: b.x, y: b.y, scale: [1, 1.08, 0.96, 1] }}
          transition={{ duration: b.d, repeat: Infinity, ease: 'easeInOut' }}
        />
      ))}
      <div className="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-canvas" />
    </div>
  )
}
