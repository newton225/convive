import { useId } from 'react';

const Width = 380;
const Height = 292;
const Gold = 'oklch(0.78 0.12 75)';
const TriangleStep = 10;

// Les pointes de la lisiere : une rangee de triangles le long du bord, vers l'interieur.
const TriangleXs = Array.from(
    { length: Math.floor((Width - 34) / TriangleStep) },
    (_, index) => 14 + index * TriangleStep,
);

/**
 * Le fond decoratif du billet de demonstration, d'inspiration africaine : des lignes en zigzag et des
 * points, dans l'esprit des tissus tisses et teints (kente, bogolan), et une lisiere de triangles
 * dores en haut et en bas du billet, comme la bordure d'un pagne. Tres discret : le motif n'est qu'une
 * texture, a peine plus claire que le fond, et il s'estompe au milieu du billet, la ou se lit le
 * texte. Purement decoratif.
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
                    id={`${id}-cloth`}
                    width="40"
                    height="28"
                    patternUnits="userSpaceOnUse"
                >
                    <g
                        fill="none"
                        stroke="white"
                        strokeWidth="0.9"
                        strokeLinejoin="round"
                    >
                        <path d="M0 10L10 2 20 10 30 2 40 10" />
                        <path
                            d="M0 16L10 8 20 16 30 8 40 16"
                            strokeOpacity="0.6"
                        />
                    </g>
                    <g fill="white">
                        {[5, 15, 25, 35].map((x) => (
                            <circle key={x} cx={x} cy="23" r="1.3" />
                        ))}
                    </g>
                </pattern>
                <linearGradient id={`${id}-fade`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="white" stopOpacity="1" />
                    <stop offset="0.5" stopColor="white" stopOpacity="0.35" />
                    <stop offset="1" stopColor="white" stopOpacity="1" />
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
                fill={`url(#${id}-cloth)`}
                opacity={0.11}
                mask={`url(#${id}-mask)`}
            />
            <g fill={Gold} fillOpacity={0.55}>
                {TriangleXs.map((x) => (
                    <g key={x}>
                        <path d={`M${x} 1.5l4 5 4-5z`} />
                        <path d={`M${x} ${Height - 1.5}l4-5 4 5z`} />
                    </g>
                ))}
            </g>
        </svg>
    );
}
