import type { HTMLAttributes, ReactNode } from "react";
import { classNames } from "../../../utils/helpers";

type TextProps = HTMLAttributes<HTMLSpanElement> & {
  children: ReactNode;
  tone?: "default" | "muted" | "accent";
};

export function Text({
  children,
  className = "",
  tone = "default",
  ...props
}: TextProps) {
  const toneClasses = {
    default: "text-[#243b53]",
    muted: "text-[#52606d]",
    accent: "text-[#9a3412]",
  } as const;

  return (
    <span
      className={classNames(
        'font-["Segoe_UI","Helvetica_Neue",Arial,sans-serif] text-[0.84rem] font-medium leading-[1.25]',
        toneClasses[tone],
        className,
      )}
      {...props}
    >
      {children}
    </span>
  );
}
