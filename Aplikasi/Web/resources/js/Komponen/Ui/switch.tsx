"use client"

import * as React from "react"
import { cn } from "@/Komponen/Ui/utils"
import { Switch as SwitchPrimitive } from "radix-ui"

function Switch({
  className,
  size = "default",
  ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root> & {
  size?: "sm" | "default"
}) {
  return (
    <SwitchPrimitive.Root
      data-slot="switch"
      data-size={size}
      className={cn(
        "peer group/switch relative inline-flex shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50",
        "p-0.5",
        "data-[size=default]:h-6 data-[size=default]:w-14",
        "data-[size=sm]:h-5 data-[size=sm]:w-11",
        "data-[state=checked]:bg-primary data-[state=unchecked]:bg-input",
        className
      )}
      {...props}
    >
      <span
        aria-hidden="true"
        className={cn(
          "pointer-events-none absolute inset-y-0 left-0.5 flex w-8 items-center justify-center select-none font-bold uppercase text-permukaan text-[10px] leading-none tracking-wider transition-opacity duration-200",
          "group-data-[size=sm]/switch:left-0.5 group-data-[size=sm]/switch:w-6 group-data-[size=sm]/switch:text-[9px]",
          "group-data-[state=checked]/switch:opacity-100 group-data-[state=unchecked]/switch:opacity-0"
        )}
      >
        ON
      </span>
      <span
        aria-hidden="true"
        className={cn(
          "pointer-events-none absolute inset-y-0 right-0.5 flex w-8 items-center justify-center select-none font-bold uppercase text-permukaan text-[10px] leading-none tracking-wider transition-opacity duration-200",
          "group-data-[size=sm]/switch:right-0.5 group-data-[size=sm]/switch:w-6 group-data-[size=sm]/switch:text-[9px]",
          "group-data-[state=checked]/switch:opacity-0 group-data-[state=unchecked]/switch:opacity-100"
        )}
      >
        OFF
      </span>
      <SwitchPrimitive.Thumb
        data-slot="switch-thumb"
        className={cn(
          "pointer-events-none relative z-10 block rounded-full bg-background ring-0 transition-transform duration-200",
          "group-data-[size=default]/switch:size-5 group-data-[size=sm]/switch:size-4",
          "group-data-[size=default]/switch:data-[state=checked]:translate-x-8 group-data-[size=sm]/switch:data-[state=checked]:translate-x-6",
          "data-[state=unchecked]:translate-x-0"
        )}
      />
    </SwitchPrimitive.Root>
  )
}

export { Switch }

