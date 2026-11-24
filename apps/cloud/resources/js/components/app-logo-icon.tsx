import type { SVGAttributes } from 'react';

/** Preflight mark: a paper plane leaving a check-shaped trail. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.8}
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <path d="M21.5 3 10.2 13.8" />
            <path
                d="M21.5 3 15 21l-4.8-7.2L3 9z"
                fill="currentColor"
                fillOpacity={0.18}
            />
            <path d="m3.5 17.5 2 2 3.5-3.5" />
        </svg>
    );
}
