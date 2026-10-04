import type { SVGAttributes } from 'react';

/**
 * The app mark: fingerprint ridges inside a clock ring (biometric attendance + time).
 * Drawn with strokes in currentColor, so it takes the colour of its text class.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.25}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path fill="none" d="M16 3.5A12.5 12.5 0 1 1 5.2 9.7" />
            <path fill="none" d="M16 3.5v3" />
            <path
                fill="none"
                d="M10.2 21.5a7.5 7.5 0 0 1-1.7-4.8 7.5 7.5 0 0 1 15 0"
            />
            <path
                fill="none"
                d="M13.2 24a10 10 0 0 1-1.2-4.6v-2.7a4 4 0 0 1 8 0v1.6"
            />
            <path fill="none" d="M16 16.7v2.8a7 7 0 0 0 1.8 4.7" />
        </svg>
    );
}
