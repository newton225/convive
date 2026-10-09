import { useId } from 'react';

const Width = 380;
const Height = 292;
const Gold = 'oklch(0.78 0.12 75)';

/**
 * Le fond decoratif du billet de demonstration : des ecailles en arcs concentriques, le motif des
 * cartons d'invitation « art deco », et quatre petits losanges or sur le filet. Tres discret : le
 * motif n'est qu'une texture, a peine plus clair que le fond, et il s'estompe au milieu du billet,
 * la ou se lit le texte. Purement decoratif.
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
                    id={`${id}-scales`}
                    width="28"
                    height="14"
                    patternUnits="userSpaceOnUse"
                >
                    <g fill="none" stroke="white" strokeWidth="0.7">
                        {[0, 14, 28].map((x) =>
                            [13, 9, 5].map((radius) => (
                                <circle
                                    key={`${x}-${radius}`}
                                    cx={x}
                                    cy={x === 14 ? 7 : 14}
                                    r={radius}
                                />
                            )),
                        )}
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
                fill={`url(#${id}-scales)`}
                opacity={0.12}
                mask={`url(#${id}-mask)`}
            />
            <g fill={Gold} fillOpacity={0.5}>
                <path d="M190 8l4 4-4 4-4-4z" />
                <path d={`M190 ${Height - 16}l4 4-4 4-4-4z`} />
                <path d={`M8 ${Height / 2}l4 4-4 4-4-4z`} />
                <path d={`M${Width - 16} ${Height / 2}l4 4-4 4-4-4z`} />
            </g>
        </svg>
    );
}
