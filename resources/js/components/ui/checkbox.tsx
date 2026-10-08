import * as CheckboxPrimitive from "@radix-ui/react-checkbox"
import { CheckIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Checkbox({
  className,
  onClick,
  ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
  return (
    <CheckboxPrimitive.Root
      data-slot="checkbox"
      // Radix signale son champ cache par un evenement `click`, que le composant Form d'Inertia
      // n'ecoute pas : sans `input`, cocher une case n'allumait pas le bouton Enregistrer. L'envoi
      // attend la mise a jour du champ cache.
      onClick={(event) => {
        onClick?.(event)
        const target = event.currentTarget
        setTimeout(() => target.dispatchEvent(new Event("input", { bubbles: true })), 0)
      }}
      className={cn(
        "peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        data-slot="checkbox-indicator"
        className="flex items-center justify-center text-current transition-none"
      >
        <CheckIcon className="size-3.5" />
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
}

export { Checkbox }
