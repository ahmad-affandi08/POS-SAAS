import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
} from "lucide-react"
import { Toaster as Sonner, type ToasterProps } from "sonner"

// Aplikasi web tidak punya mode gelap: tema Sonner dikunci terang (tanpa next-themes).
// Warna mengikuti token di Gaya/Aplikasi.css lewat variabel --popover, --border, dst.
const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      theme="light"
      className="toaster group"
      icons={{
        success: <CircleCheckIcon className="size-4" />,
        info: <InfoIcon className="size-4" />,
        warning: <TriangleAlertIcon className="size-4" />,
        error: <OctagonXIcon className="size-4" />,
        loading: <Loader2Icon className="size-4 animate-spin" />,
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
          "--success-bg": "var(--color-sukses-lembut)",
          "--success-text": "var(--color-sukses)",
          "--success-border": "var(--color-sukses)",
          "--info-bg": "var(--color-info-lembut)",
          "--info-text": "var(--color-info)",
          "--info-border": "var(--color-info)",
          "--warning-bg": "var(--color-peringatan-lembut)",
          "--warning-text": "var(--color-peringatan)",
          "--warning-border": "var(--color-peringatan)",
          "--error-bg": "var(--color-bahaya-lembut)",
          "--error-text": "var(--color-bahaya)",
          "--error-border": "var(--color-bahaya)",
        } as React.CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
