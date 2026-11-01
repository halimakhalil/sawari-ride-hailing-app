import type { Place, RoutePoint } from '../api/types'

// Viewport over central Lahore; coordinates are projected linearly into the SVG.
const BOUNDS = { minLat: 31.43, maxLat: 31.61, minLng: 74.24, maxLng: 74.48 }
const WIDTH = 600
const HEIGHT = 450

function project(lat: number, lng: number): { x: number; y: number } {
  return {
    x: ((lng - BOUNDS.minLng) / (BOUNDS.maxLng - BOUNDS.minLng)) * WIDTH,
    y: ((BOUNDS.maxLat - lat) / (BOUNDS.maxLat - BOUNDS.minLat)) * HEIGHT,
  }
}

/** Minimum distance (SVG units) between labels so nearby places don't print on top of each other. */
const LABEL_SPACING = 34

/**
 * Greedy declutter: the selected pins get their own labels, then each catalogue
 * place is labelled only if no label has been placed close to it already.
 */
function placeMarkers(places: Place[], pins: ({ x: number; y: number } | null)[]) {
  const taken = pins.filter((p): p is { x: number; y: number } => p !== null)

  return places.map((place) => {
    const { x, y } = project(place.latitude, place.longitude)
    const labelled = taken.every((t) => Math.hypot(t.x - x, t.y - y) >= LABEL_SPACING)
    if (labelled) taken.push({ x, y })
    return { place, x, y, labelled }
  })
}

function PinLabel({ x, y, text }: { x: number; y: number; text: string }) {
  const width = text.length * 7 + 16
  return (
    <g transform={`translate(${x + 14} ${y - 26})`}>
      <rect width={width} height="22" rx="6" fill="#ffffff" stroke="#e2e8f0" />
      <text x="8" y="15" fontSize="12" fontWeight="600" fill="#0f172a">
        {text}
      </text>
    </g>
  )
}

interface Props {
  places: Place[]
  pickup: RoutePoint | null
  destination: RoutePoint | null
  className?: string
}

/** A stylised map of Lahore standing in for a real map SDK. */
export function MapPlaceholder({ places, pickup, destination, className = '' }: Props) {
  const from = pickup ? project(pickup.latitude, pickup.longitude) : null
  const to = destination ? project(destination.latitude, destination.longitude) : null
  const label = pickup && destination ? `Map showing route from ${pickup.address} to ${destination.address}` : 'Map of Lahore'

  return (
    <div className={`relative overflow-hidden bg-[#e8efe9] ${className}`}>
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} preserveAspectRatio="xMidYMid slice" className="size-full" role="img" aria-label={label}>
        <defs>
          <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M40 0H0V40" fill="none" stroke="#d5e0d8" strokeWidth="1" />
          </pattern>
        </defs>
        <rect width={WIDTH} height={HEIGHT} fill="url(#grid)" />

        {/* River Ravi along the north-west */}
        <path d="M-10 60 C 60 40, 90 110, 150 90 S 230 20, 300 -10" fill="none" stroke="#a9cfe8" strokeWidth="18" strokeLinecap="round" />
        {/* Lahore canal running north-east to south-west */}
        <path d="M520 -10 C 470 120, 380 200, 330 280 S 230 420, 180 470" fill="none" stroke="#bcdcef" strokeWidth="6" />
        {/* Arterial roads */}
        <g fill="none" stroke="#ffffff" strokeLinecap="round">
          <path d="M150 60 L 420 380" strokeWidth="9" />
          <path d="M40 300 L 600 180" strokeWidth="9" />
          <path d="M260 0 L 300 450" strokeWidth="7" />
          <path d="M0 200 C 200 220, 380 260, 600 330" strokeWidth="6" />
        </g>
        {/* Parks */}
        <circle cx="330" cy="215" r="26" fill="#cfe5d3" />
        <circle cx="470" cy="300" r="18" fill="#cfe5d3" />

        {placeMarkers(places, [from, to]).map(({ place, x, y, labelled }) => (
          <g key={place.id}>
            <circle cx={x} cy={y} r="4" fill="#64748b" opacity="0.55" />
            {labelled && (
              <text x={x + 7} y={y + 4} fontSize="11" fill="#475569" opacity="0.8">
                {place.name}
              </text>
            )}
          </g>
        ))}

        {from && to && (
          <path
            d={`M${from.x} ${from.y} Q ${(from.x + to.x) / 2 + (to.y - from.y) * 0.2} ${(from.y + to.y) / 2 - (to.x - from.x) * 0.2} ${to.x} ${to.y}`}
            fill="none"
            stroke="#047857"
            strokeWidth="4"
            strokeDasharray="10 8"
            strokeLinecap="round"
          />
        )}
        {from && (
          <g>
            <circle cx={from.x} cy={from.y} r="16" fill="#059669" opacity="0.18" />
            <circle cx={from.x} cy={from.y} r="8" fill="#ffffff" stroke="#047857" strokeWidth="4" />
          </g>
        )}
        {to && <rect x={to.x - 8} y={to.y - 8} width="16" height="16" rx="3" fill="#0f172a" stroke="#ffffff" strokeWidth="3" />}
        {from && pickup && <PinLabel x={from.x} y={from.y} text={pickup.address} />}
        {to && destination && <PinLabel x={to.x} y={to.y} text={destination.address} />}
      </svg>

      <span className="absolute right-3 bottom-3 rounded-md bg-white/85 px-2 py-1 text-[11px] font-medium text-slate-600 shadow-sm">
        Lahore · map preview
      </span>
    </div>
  )
}
