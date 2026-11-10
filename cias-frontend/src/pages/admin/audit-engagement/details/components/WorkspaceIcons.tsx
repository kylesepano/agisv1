type WorkspaceIconProps = {
  size?: number;
  className?: string;
};

function gearPath(cx: number, cy: number, rootRadius: number, toothRadius: number, holeRadius: number) {
  const points = Array.from({ length: 32 }, (_, index) => {
    const angle = -Math.PI / 2 + index * Math.PI / 16;
    const radius = index % 4 === 1 || index % 4 === 2 ? toothRadius : rootRadius;
    return `${(cx + Math.cos(angle) * radius).toFixed(2)} ${(cy + Math.sin(angle) * radius).toFixed(2)}`;
  });

  return `M ${points.join(" L ")} Z M ${cx - holeRadius} ${cy} a ${holeRadius} ${holeRadius} 0 1 0 ${holeRadius * 2} 0 a ${holeRadius} ${holeRadius} 0 1 0 ${-holeRadius * 2} 0 Z`;
}

export function PlanningIcon({ size = 96, className }: WorkspaceIconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 96 96" fill="none" aria-hidden="true" focusable="false" className={className}>
      <path d="M32 51h19M51 26v49M51 26h16M51 75h16" stroke="currentColor" strokeWidth="6" strokeLinejoin="miter" />
      <rect x="7" y="39" width="25" height="24" stroke="currentColor" strokeWidth="6" />
      <rect x="67" y="15" width="24" height="22" stroke="currentColor" strokeWidth="6" />
      <rect x="67" y="64" width="24" height="22" stroke="currentColor" strokeWidth="6" />
    </svg>
  );
}

export function ExecutionIcon({ size = 96, className }: WorkspaceIconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 96 96" aria-hidden="true" focusable="false" className={className}>
      <path d={gearPath(30, 29, 14, 20, 6)} fill="currentColor" fillRule="evenodd" />
      <path d={gearPath(63, 62, 21, 29, 9)} fill="currentColor" fillRule="evenodd" />
    </svg>
  );
}

export function ReportingIcon({ size = 96, className }: WorkspaceIconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 96 96" fill="none" aria-hidden="true" focusable="false" className={className}>
      <path d="M8 53 38 22 55 39 88 7" stroke="currentColor" strokeWidth="6.5" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M16 72v13M33 61v24M50 72v13M67 60v25M84 48v37" stroke="currentColor" strokeWidth="6.5" strokeLinecap="round" />
    </svg>
  );
}

export function CompletionIcon({ size = 96, className }: WorkspaceIconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 96 96" fill="none" aria-hidden="true" focusable="false" className={className}>
      <path d="M62 15H21a8 8 0 0 0-8 8v54a8 8 0 0 0 8 8h49a8 8 0 0 0 8-8V50" stroke="currentColor" strokeWidth="7" strokeLinecap="square" strokeLinejoin="round" />
      <path d="m37 49 16 15 31-33" stroke="currentColor" strokeWidth="7" strokeLinecap="square" strokeLinejoin="miter" />
    </svg>
  );
}
