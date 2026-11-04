import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Canvas, useFrame, useThree } from '@react-three/fiber'
import { ContactShadows, Environment, Lightformer, OrbitControls, RoundedBox } from '@react-three/drei'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import * as THREE from 'three'
import type { MenuCategory } from '../lib/types'

// 3D preview of the buffet line, drawn entirely in code (no downloaded models).
// One serving slot per dish the package includes; each picked dish drops into
// its slot as a steel chafing tray, bamboo steamer, dessert bowl or drinks
// dispenser, with a food colour based on the dish. The plate stack grows with
// the guest count. Loaded lazily and only on desktop, so phones never download
// three.js. Renders on demand (not every frame) to save battery.

interface Slot {
  key: string
  category: string
  dish?: { id: number; name: string }
  x: number
  z: number
}

type Vessel = 'tray' | 'steamer' | 'bowl' | 'plate' | 'dispenser'

function vesselFor(category: string, dishName: string): Vessel {
  if (category === 'Dim Sum') return 'steamer'
  if (category === 'Drinks') return 'dispenser'
  if (category === 'Desserts') return /tart|kueh/i.test(dishName) ? 'plate' : 'bowl'
  return 'tray'
}

// Food colours by keyword, falling back to a colour per course.
const keywordColours: [RegExp, string][] = [
  [/rendang|mutton|black pepper/i, '#7a3a1d'],
  [/masak merah|asam|sambal sotong|sambal prawn/i, '#b8432a'],
  [/satay|ayam goreng|cereal/i, '#b9772f'],
  [/sweet and sour/i, '#d9772c'],
  [/broccoli|kangkong/i, '#4d7c2c'],
  [/lodeh/i, '#d8b44a'],
  [/chap chye/i, '#a8743c'],
  [/kuning|briyani/i, '#e2b440'],
  [/lemak|bee hoon|yang chow/i, '#efe2c0'],
  [/goreng|mee/i, '#c98a3a'],
  [/spring roll|ngoh hiang|bergedil/i, '#c8893f'],
  [/otah/i, '#d0612f'],
  [/achar/i, '#e1b23c'],
  [/chendol/i, '#7fae5a'],
  [/cha cha|pulut/i, '#6b4a6e'],
  [/mango/i, '#f2b13b'],
  [/almond|longan/i, '#f4efe2'],
  [/egg tart/i, '#f0c64a'],
  [/kueh/i, '#5fa36a'],
  [/lemon tea/i, '#b5651d'],
  [/bandung/i, '#f2a2b8'],
  [/chrysanthemum/i, '#e3c76a'],
  [/teh tarik/i, '#c49a6c'],
  [/chinese tea/i, '#8a5a2b'],
  [/har gow/i, '#f3ddd0'],
  [/siew mai/i, '#efc24a'],
  [/lo mai kai/i, '#76884a'],
  [/lo bak go/i, '#e6d2a0'],
  [/vegetable dumpling/i, '#cfe2b6'],
]

const courseColours: Record<string, string> = {
  'Rice & Noodles': '#e5c77d',
  Mains: '#a4502a',
  Vegetables: '#55833a',
  Sides: '#c98a3f',
  'Dim Sum': '#f5efe2',
  Desserts: '#e9a9bb',
  Drinks: '#c47a3c',
}

function foodColour(category: string, name: string) {
  return keywordColours.find(([re]) => re.test(name))?.[1] ?? courseColours[category] ?? '#c98a3f'
}

/** Small deterministic random numbers, so a dish always looks the same. */
function seeded(seed: number) {
  let s = seed * 9301 + 49297
  return () => {
    s = (s * 9301 + 49297) % 233280
    return s / 233280
  }
}

// Brushed steel and white porcelain. Metalness stays below 1 so trays still read
// as light grey on machines where the reflection map is weak.
const steel = { color: '#eef0f2', metalness: 0.55, roughness: 0.3 }
const darkSteel = { color: '#c9ced3', metalness: 0.5, roughness: 0.4 }
const ceramic = { color: '#ffffff', metalness: 0, roughness: 0.35 }

function Bumps({ seed, colour, count, spread, y, size }: { seed: number; colour: string; count: number; spread: [number, number]; y: number; size: number }) {
  const items = useMemo(() => {
    const rnd = seeded(seed)
    const base = new THREE.Color(colour)
    return Array.from({ length: count }, () => ({
      pos: [(rnd() - 0.5) * spread[0], y, (rnd() - 0.5) * spread[1]] as [number, number, number],
      r: size * (0.7 + rnd() * 0.6),
      colour: base.clone().offsetHSL(0, 0, (rnd() - 0.5) * 0.12),
    }))
  }, [seed, colour, count, spread, y, size])
  return (
    <>
      {items.map((b, i) => (
        <mesh key={i} position={b.pos} scale={[1, 0.55, 1]}>
          <sphereGeometry args={[b.r, 12, 10]} />
          <meshStandardMaterial color={b.colour} roughness={0.7} />
        </mesh>
      ))}
    </>
  )
}

function DishModel({ category, dish }: { category: string; dish: { id: number; name: string } }) {
  const vessel = vesselFor(category, dish.name)
  const colour = foodColour(category, dish.name)

  switch (vessel) {
    case 'steamer':
      return (
        <group>
          <mesh position-y={0.1}>
            <cylinderGeometry args={[0.36, 0.36, 0.2, 40, 1, true]} />
            <meshStandardMaterial color="#c9a26b" roughness={0.85} side={THREE.DoubleSide} />
          </mesh>
          <mesh position-y={0.03}>
            <cylinderGeometry args={[0.35, 0.35, 0.02, 40]} />
            <meshStandardMaterial color="#b88f58" roughness={0.9} />
          </mesh>
          {Array.from({ length: 5 }, (_, i) => {
            const a = (i / 5) * Math.PI * 2
            return (
              <mesh key={i} position={[Math.cos(a) * 0.19, 0.1, Math.sin(a) * 0.19]} scale={[1, 0.8, 1]}>
                <sphereGeometry args={[0.085, 16, 12]} />
                <meshStandardMaterial color={colour} roughness={0.55} />
              </mesh>
            )
          })}
          <mesh position-y={0.1}>
            <sphereGeometry args={[0.085, 16, 12]} />
            <meshStandardMaterial color={colour} roughness={0.55} />
          </mesh>
        </group>
      )
    case 'bowl':
      return (
        <group>
          <mesh position-y={0.11}>
            <cylinderGeometry args={[0.36, 0.2, 0.22, 40, 1, true]} />
            <meshStandardMaterial {...ceramic} side={THREE.DoubleSide} />
          </mesh>
          <mesh position-y={0.005}>
            <cylinderGeometry args={[0.2, 0.2, 0.01, 32]} />
            <meshStandardMaterial {...ceramic} />
          </mesh>
          <mesh position-y={0.18}>
            <cylinderGeometry args={[0.33, 0.33, 0.02, 40]} />
            <meshStandardMaterial color={colour} roughness={0.4} />
          </mesh>
        </group>
      )
    case 'plate':
      return (
        <group>
          <mesh position-y={0.015}>
            <cylinderGeometry args={[0.42, 0.38, 0.03, 48]} />
            <meshStandardMaterial {...ceramic} />
          </mesh>
          {Array.from({ length: 7 }, (_, i) => {
            const a = (i / 7) * Math.PI * 2
            const r = i === 0 ? 0 : 0.22
            return (
              <mesh key={i} position={[Math.cos(a) * r, 0.06, Math.sin(a) * r]}>
                <cylinderGeometry args={[0.075, 0.065, 0.06, 20]} />
                <meshStandardMaterial color={new THREE.Color(colour).offsetHSL(0, 0, (i % 3) * 0.05)} roughness={0.6} />
              </mesh>
            )
          })}
        </group>
      )
    case 'dispenser':
      return (
        <group>
          <RoundedBox args={[0.5, 0.08, 0.5]} radius={0.02} position-y={0.04}>
            <meshStandardMaterial {...darkSteel} />
          </RoundedBox>
          <mesh position-y={0.36}>
            <cylinderGeometry args={[0.2, 0.2, 0.5, 40]} />
            <meshStandardMaterial color={colour} roughness={0.2} transparent opacity={0.92} />
          </mesh>
          <mesh position-y={0.46}>
            <cylinderGeometry args={[0.23, 0.23, 0.76, 40, 1, true]} />
            <meshPhysicalMaterial color="#ffffff" roughness={0.05} metalness={0} transparent opacity={0.22} side={THREE.DoubleSide} />
          </mesh>
          <mesh position-y={0.86}>
            <cylinderGeometry args={[0.24, 0.24, 0.05, 40]} />
            <meshStandardMaterial {...steel} />
          </mesh>
          <mesh position={[0, 0.14, 0.25]}>
            <boxGeometry args={[0.06, 0.08, 0.08]} />
            <meshStandardMaterial {...steel} />
          </mesh>
        </group>
      )
    default:
      return (
        <group>
          <RoundedBox args={[0.98, 0.1, 0.68]} radius={0.02} position-y={0.05}>
            <meshStandardMaterial {...darkSteel} />
          </RoundedBox>
          <RoundedBox args={[0.92, 0.16, 0.62]} radius={0.03} position-y={0.2}>
            <meshStandardMaterial {...steel} />
          </RoundedBox>
          <mesh position-y={0.275}>
            <boxGeometry args={[0.84, 0.02, 0.54]} />
            <meshStandardMaterial color={colour} roughness={0.75} />
          </mesh>
          <Bumps seed={dish.id} colour={colour} count={14} spread={[0.72, 0.42]} y={0.29} size={0.07} />
        </group>
      )
  }
}

/** Drops its children into place when they first appear. */
function DropIn({ children }: { children: ReactNode }) {
  const ref = useRef<THREE.Group>(null)
  const t = useRef(0)
  const reduced = useReducedMotion()
  const invalidate = useThree((s) => s.invalidate)

  useEffect(() => invalidate(), [invalidate])

  useFrame((_, delta) => {
    const g = ref.current
    if (!g || t.current >= 1) return
    t.current = reduced ? 1 : Math.min(1, t.current + Math.min(delta, 0.05) / 0.6)
    const p = t.current
    const fall = 1 - Math.pow(1 - p, 3) // ease out
    const c = 1.70158 // ease out back, for a little bounce in scale
    const pop = 1 + (c + 1) * Math.pow(p - 1, 3) + c * Math.pow(p - 1, 2)
    g.position.y = (1 - fall) * 0.9
    g.scale.setScalar(0.6 + 0.4 * pop)
    invalidate()
  })

  return (
    <group ref={ref} position-y={reduced ? 0 : 0.9} scale={reduced ? 1 : 0.6}>
      {children}
    </group>
  )
}

function SlotView({ slot, onHover }: { slot: Slot; onHover: (key: string | null) => void }) {
  return (
    <group position={[slot.x, 0, slot.z]}>
      {/* Empty place setting, so customers see how many dishes are left. */}
      <RoundedBox args={[0.94, 0.006, 0.64]} radius={0.002} position-y={0.003}>
        <meshStandardMaterial color={slot.dish ? '#f1eee9' : '#e4ded4'} roughness={1} />
      </RoundedBox>
      {slot.dish && (
        <group
          onPointerOver={(e) => {
            e.stopPropagation()
            onHover(slot.key)
          }}
          onPointerOut={() => onHover(null)}
        >
          <DropIn key={slot.dish.id}>
            <DishModel category={slot.category} dish={slot.dish} />
          </DropIn>
        </group>
      )}
    </group>
  )
}

function PlateStack({ guests, x }: { guests: number; x: number }) {
  const count = Math.max(4, Math.min(40, Math.round(guests / 8)))
  return (
    <group position={[x, 0, 0]}>
      {Array.from({ length: count }, (_, i) => (
        <group key={i} position-y={0.011 + i * 0.022}>
          <mesh>
            <cylinderGeometry args={[0.32, 0.27, 0.016, 40]} />
            <meshStandardMaterial {...ceramic} />
          </mesh>
          <mesh>
            <cylinderGeometry args={[0.325, 0.325, 0.006, 40]} />
            <meshStandardMaterial color="#d6dce3" roughness={0.5} />
          </mesh>
        </group>
      ))}
    </group>
  )
}

function CameraRig({ width, depth }: { width: number; depth: number }) {
  const camera = useThree((s) => s.camera) as THREE.PerspectiveCamera
  const size = useThree((s) => s.size)
  const invalidate = useThree((s) => s.invalidate)

  useEffect(() => {
    const aspect = size.width / size.height
    const vfov = THREE.MathUtils.degToRad(camera.fov)
    const hfov = 2 * Math.atan(Math.tan(vfov / 2) * aspect)
    const fitWidth = (width / 2 + 0.25) / Math.tan(hfov / 2)
    const fitDepth = (depth / 2 + 0.6) / Math.tan(vfov / 2)
    const distance = Math.max(fitWidth, fitDepth) * 1.02
    camera.position.copy(new THREE.Vector3(0, 1.05, 1).normalize().multiplyScalar(distance))
    camera.lookAt(0, -0.12, 0)
    camera.updateProjectionMatrix()
    invalidate()
  }, [camera, size, width, depth, invalidate])

  return null
}

export default function BuffetPreview({
  menu,
  selected,
  guests,
}: {
  menu: MenuCategory[]
  selected: number[]
  guests: number
}) {
  const [hovered, setHovered] = useState<string | null>(null)
  const [recent, setRecent] = useState<number | null>(null)
  const previous = useRef<number[]>(selected)

  // Briefly label the dish that was just added.
  useEffect(() => {
    const added = selected.find((id) => !previous.current.includes(id))
    previous.current = selected
    if (added === undefined) return
    setRecent(added)
    const timer = setTimeout(() => setRecent(null), 1800)
    return () => clearTimeout(timer)
  }, [selected])

  const layout = useMemo(() => {
    const raw: Omit<Slot, 'x' | 'z'>[] = []
    for (const cat of menu) {
      const picks = selected
        .map((id) => cat.dishes.find((d) => d.id === id))
        .filter((d): d is NonNullable<typeof d> => !!d)
      for (let i = 0; i < cat.choose; i++) {
        raw.push({ key: `${cat.id}-${i}`, category: cat.name, dish: picks[i] && { id: picks[i].id, name: picks[i].name } })
      }
    }
    const rows = raw.length > 6 ? 2 : 1
    const cols = Math.ceil(raw.length / rows)
    const gapX = 1.12
    const slots: Slot[] = raw.map((s, i) => {
      const row = Math.floor(i / cols)
      const col = i % cols
      return { ...s, x: (col - (cols - 1) / 2) * gapX, z: rows === 2 ? (row === 0 ? -0.48 : 0.48) : 0 }
    })
    const slotsLeft = -((cols - 1) / 2) * gapX - 0.5
    const plateX = slotsLeft - 0.65
    const left = plateX - 0.5
    const right = ((cols - 1) / 2) * gapX + 0.6
    const width = right - left
    const depth = rows * 0.96 + 0.5
    const centre = (left + right) / 2
    return { slots, plateX, width, depth, centre }
  }, [menu, selected])

  // Caption under the scene: the dish under the pointer, else the one just added.
  const caption =
    layout.slots.find((s) => s.key === hovered)?.dish?.name ??
    layout.slots.find((s) => s.dish && s.dish.id === recent)?.dish?.name ??
    null
  const filled = layout.slots.filter((s) => s.dish).length

  return (
    <div className="relative h-full">
      <Canvas
        frameloop="demand"
        dpr={[1, 2]}
        camera={{ fov: 30, position: [0, 4, 8] }}
        gl={{ antialias: true, powerPreference: 'low-power' }}
        onCreated={({ gl }) => {
          // Neutral tone mapping keeps white porcelain white (the default filmic curve greys it).
          gl.toneMapping = THREE.NeutralToneMapping
        }}
        aria-label={`3D preview of your buffet line: ${filled} of ${layout.slots.length} dishes placed, plates for ${guests} guests`}
        role="img"
      >
        <CameraRig width={layout.width} depth={layout.depth} />
        <hemisphereLight args={['#ffffff', '#f3e9dc', 1.1]} />
        <directionalLight position={[3, 7, 5]} intensity={1.6} />
        <directionalLight position={[-4, 3, -2]} intensity={0.5} />
        <Environment resolution={256} frames={1}>
          <group rotation={[-Math.PI / 2, 0, 0]}>
            <Lightformer intensity={2} rotation-x={Math.PI / 2} position={[0, 5, -9]} scale={[12, 12, 1]} />
            <Lightformer intensity={1.5} rotation-y={Math.PI / 2} position={[-6, 1, -1]} scale={[12, 2, 1]} />
            <Lightformer intensity={1.5} rotation-y={-Math.PI / 2} position={[8, 1, 0]} scale={[16, 2, 1]} />
            <Lightformer form="ring" color="#ffd7b0" intensity={3} scale={3} position={[4, 4, 6]} />
          </group>
        </Environment>

        <group position-x={-layout.centre}>
          {/* Table top with a short linen skirt */}
          <RoundedBox args={[layout.width, 0.05, layout.depth]} radius={0.02} position={[layout.centre, -0.025, 0]}>
            <meshStandardMaterial color="#ffffff" roughness={0.7} />
          </RoundedBox>
          <mesh position={[layout.centre, -0.13, 0]}>
            <boxGeometry args={[layout.width - 0.03, 0.16, layout.depth - 0.03]} />
            <meshStandardMaterial color="#f4efe7" roughness={1} />
          </mesh>

          <PlateStack guests={guests} x={layout.plateX} />
          {layout.slots.map((slot) => (
            <SlotView key={slot.key} slot={slot} onHover={setHovered} />
          ))}
        </group>

        <ContactShadows position-y={-0.22} scale={[layout.width * 1.5, layout.depth * 2.5]} blur={2.6} opacity={0.3} far={1} />
        <OrbitControls
          makeDefault
          target={[0, -0.12, 0]}
          enablePan={false}
          enableZoom={false}
          minPolarAngle={0.35}
          maxPolarAngle={1.2}
          minAzimuthAngle={-0.9}
          maxAzimuthAngle={0.9}
          enableDamping
        />
      </Canvas>
      <div className="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center" aria-live="polite">
        <AnimatePresence mode="wait">
          {caption && (
            <motion.p
              key={caption}
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -4 }}
              transition={{ duration: 0.2 }}
              className="rounded-full bg-ink/85 px-3 py-1 text-[12px] font-medium text-white shadow-lg backdrop-blur"
            >
              {caption}
            </motion.p>
          )}
        </AnimatePresence>
      </div>
    </div>
  )
}
