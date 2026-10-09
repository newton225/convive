import { useId } from 'react';

const Width = 380;
const Height = 292;
const Gold = 'oklch(0.78 0.12 75)';

type RosetteProps = {
    cx: number;
    cy: number;
    rx: number;
    ry: number;
    count: number;
    stroke: string;
    opacity: number;
};

// Une rosace de guillochis : des ellipses identiques tournees d'un meme pas, le motif des billets de
// banque et des titres, qui se copie mal et se lit comme un document de valeur.
function Rosette({ cx, cy, rx, ry, count, stroke, opacity }: RosetteProps) {
    return (
        <g
            fill="none"
            stroke={stroke}
            strokeOpacity={opacity}
            strokeWidth={0.8}
        >
            {Array.from({ length: count }, (_, index) => (
                <ellipse
                    key={index}
                    cx={cx}
                    cy={cy}
                    rx={rx}
                    ry={ry}
                    transform={`rotate(${(index * 180) / count} ${cx} ${cy})`}
                />
            ))}
        </g>
    );
}

/**
 * Le fond decoratif du billet de demonstration : un fin hachurage en diagonale, deux rosaces de
 * guillochis dans les coins et quatre losanges sur le filet. Purement decoratif, tres discret, et
 * sans jamais passer sous un texte important : les rosaces restent dans les coins.
 */
export function TicketPattern() {
    const id = useId().replace(/:/g, '');

    return (
        <svg
            viewBox={`0 0 ${Width} ${Height}`}
            preserveAspectRatio="xMidYMid slice"
            className="size-full"
            aria-hidden="true"
        >
            <defs>
                <pattern
                    id={`${id}-hatch`}
                    width="7"
                    height="7"
                    patternUnits="userSpaceOnUse"
                    patternTransform="rotate(45)"
                >
                    <line
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="7"
                        stroke="white"
                        strokeOpacity="0.045"
                        strokeWidth="1"
                    />
                </pattern>
                <linearGradient id={`${id}-fade`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="white" stopOpacity="1" />
                    <stop offset="0.55" stopColor="white" stopOpacity="0.25" />
                    <stop offset="1" stopColor="white" stopOpacity="0.9" />
                </linearGradient>
                <mask id={`${id}-mask`}>
                    <rect
                        width={Width}
                        height={Height}
                        fill={`url(#${id}-fade)`}
                    />
                </mask>
            </defs>
            <rect
                width={Width}
                height={Height}
                fill={`url(#${id}-hatch)`}
                mask={`url(#${id}-mask)`}
            />
            <Rosette
                cx={338}
                cy={34}
                rx={96}
                ry={36}
                count={18}
                stroke={Gold}
                opacity={0.3}
            />
            <Rosette
                cx={338}
                cy={34}
                rx={60}
                ry={22}
                count={18}
                stroke={Gold}
                opacity={0.22}
            />
            <Rosette
                cx={22}
                cy={276}
                rx={70}
                ry={26}
                count={14}
                stroke="white"
                opacity={0.14}
            />
            <g fill={Gold} fillOpacity={0.55}>
                <path d="M190 8l4 4-4 4-4-4z" />
                <path d={`M190 ${Height - 16}l4 4-4 4-4-4z`} />
                <path d={`M8 ${Height / 2}l4 4-4 4-4-4z`} />
                <path d={`M${Width - 16} ${Height / 2}l4 4-4 4-4-4z`} />
            </g>
        </svg>
    );
}
