import * as LabelPrimitive from "@radix-ui/react-label"
import * as React from "react"

import { RequiredMark } from "@/components/required-mark"
import { cn } from "@/lib/utils"

// `required` ajoute au composant shadcn (decision du 2026-10-07) : tout champ obligatoire de la
// plateforme le signale de la meme facon, sans que chaque formulaire reecrive l'asterisque.
function Label({
  className,
  required = false,
  children,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & { required?: boolean }) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    >
      {children}
      {required ? <RequiredMark /> : null}
    </LabelPrimitive.Root>
  )
}

export { Label }
