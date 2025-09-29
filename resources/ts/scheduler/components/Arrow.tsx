// Arrow.tsx (egyszerű FS nyíl)
export function Arrow({ fromRect, toRect }: { fromRect: DOMRect; toRect: DOMRect }) {
  const x1 = fromRect.right; const y1 = fromRect.top + fromRect.height/2;
  const x2 = toRect.left;   const y2 = toRect.top + toRect.height/2;
  const mx = (x1 + x2)/2;

  const d = `M ${x1} ${y1} L ${mx} ${y1} L ${mx} ${y2} L ${x2-6} ${y2}`;
  return (
    <g>
      <path d={d} fill="none" strokeWidth={2} strokeOpacity={0.8}/>
      <polygon points={`${x2},${y2} ${x2-6},${y2-4} ${x2-6},${y2+4}`} />
    </g>
  );
}
