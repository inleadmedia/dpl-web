import * as React from "react";

/** Primary accent — pink / orange palette. */
const FRAME = "#ff2d55";
const FRAME_DARK = "#e11d48";
const HANDLE = "#ff9500";
const HANDLE_DARK = "#ea580c";

/** Network node colors (clockwise from top). */
const NODES = [
  { angle: -90, color: "#ff2d55" },
  { angle: -45, color: "#f472b6" },
  { angle: 0, color: "#ff9500" },
  { angle: 45, color: "#fb923c" },
  { angle: 90, color: "#ff6b35" },
  { angle: 135, color: "#fda4af" },
  { angle: 180, color: "#ec4899" },
  { angle: 225, color: "#f97316" }
] as const;

const CX = 10.5;
const CY = 10.5;
const SPOKE_INNER = 2.4;
const SPOKE_OUTER = 5.6;

const toRad = (deg: number) => (deg * Math.PI) / 180;

const spokePoint = (radius: number, angleDeg: number) => ({
  x: CX + radius * Math.cos(toRad(angleDeg)),
  y: CY + radius * Math.sin(toRad(angleDeg))
});

/** Magnifying glass with AI network — matches reference icon style. */
const AiSearchIcon: React.FC = () => (
  <span className="header__menu-search-ai-icon__animated" aria-hidden="true">
    <span className="header__menu-search-ai-icon__glow" />
    <svg
      className="header__menu-search-ai-icon__mark"
      viewBox="0 0 24 24"
      width={22}
      height={22}
      aria-hidden="true"
    >
      {/* Lens */}
      <circle
        cx={CX}
        cy={CY}
        r={7.6}
        fill="#ffffff"
        stroke={FRAME}
        strokeWidth={1.85}
      />

      {/* Handle */}
      <path
        d="M15.9 15.9 L21.6 21.6"
        fill="none"
        stroke={HANDLE}
        strokeWidth={2.2}
        strokeLinecap="round"
      />
      <path
        d="M16.8 16.8 L20.7 20.7"
        fill="none"
        stroke={HANDLE_DARK}
        strokeWidth={0.9}
        strokeLinecap="round"
        opacity={0.55}
      />

      {/* Neural network */}
      <g className="header__menu-search-ai-icon__network">
        {NODES.map(({ angle, color }) => {
          const inner = spokePoint(SPOKE_INNER, angle);
          const outer = spokePoint(SPOKE_OUTER, angle);

          return (
            <g key={angle}>
              <line
                x1={inner.x}
                y1={inner.y}
                x2={outer.x}
                y2={outer.y}
                stroke={color}
                strokeWidth={0.85}
                strokeLinecap="round"
                opacity={0.95}
              />
              <circle cx={outer.x} cy={outer.y} r={1.05} fill={color} />
            </g>
          );
        })}

        <circle
          cx={CX}
          cy={CY}
          r={2.15}
          fill="none"
          stroke={FRAME}
          strokeWidth={0.75}
        />
        <path
          fill="none"
          stroke={FRAME}
          strokeWidth={0.65}
          strokeLinejoin="round"
          d="M10.5 8.55 L11.35 10.05 L12.95 10.05 L11.65 11.1 L12.05 12.65 L10.5 11.75 L8.95 12.65 L9.35 11.1 L8.05 10.05 L9.65 10.05 Z"
        />
        <circle cx={CX} cy={CY} r={0.55} fill={FRAME_DARK} />
      </g>
    </svg>
  </span>
);

export default AiSearchIcon;
