export type RgbColor = {
  r: number;
  g: number;
  b: number;
};

export type ContrastLevel = "AA" | "AAA";
export type ContrastTextSize = "normal" | "large";

const WCAG_THRESHOLDS: Record<
  ContrastLevel,
  Record<ContrastTextSize, number>
> = {
  AA: {
    normal: 4.5,
    large: 3,
  },
  AAA: {
    normal: 7,
    large: 4.5,
  },
};

const channelToLinear = (channel: number): number => {
  const value = channel / 255;
  return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
};

/**
 * Parse hex (#fff, #ffffff) or rgb/rgba() into RGB channels (0–255).
 */
export const parseColorToRgb = (color: string): RgbColor | null => {
  const normalized = color.trim().toLowerCase();

  const hexMatch = normalized.match(
    /^#([\da-f]{3}|[\da-f]{6}|[\da-f]{8})$/i,
  );
  if (hexMatch) {
    let hex = hexMatch[1];
    if (hex.length === 3) {
      hex = hex
        .split("")
        .map((char) => `${char}${char}`)
        .join("");
    }
    // Ignore alpha channel if present (#rrggbbaa).
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);
    return { r, g, b };
  }

  const rgbMatch = normalized.match(
    /^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*[\d.]+)?\s*\)$/,
  );
  if (rgbMatch) {
    return {
      r: Number(rgbMatch[1]),
      g: Number(rgbMatch[2]),
      b: Number(rgbMatch[3]),
    };
  }

  return null;
};

/**
 * Relative luminance per WCAG 2.x (0–1).
 * @see https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
 */
export const getRelativeLuminance = (color: string | RgbColor): number => {
  const rgb = typeof color === "string" ? parseColorToRgb(color) : color;
  if (!rgb) {
    throw new Error(`Unsupported color format: ${String(color)}`);
  }

  const r = channelToLinear(rgb.r);
  const g = channelToLinear(rgb.g);
  const b = channelToLinear(rgb.b);

  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * Contrast ratio between two colors (1–21).
 * Order does not matter.
 * @see https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 */
export const getContrastRatio = (
  foreground: string | RgbColor,
  background: string | RgbColor,
): number => {
  const foregroundLuminance = getRelativeLuminance(foreground);
  const backgroundLuminance = getRelativeLuminance(background);
  const lighter = Math.max(foregroundLuminance, backgroundLuminance);
  const darker = Math.min(foregroundLuminance, backgroundLuminance);

  return (lighter + 0.05) / (darker + 0.05);
};

/**
 * Whether the contrast meets WCAG AA/AAA for normal or large text.
 */
export const meetsContrastRequirement = (
  foreground: string | RgbColor,
  background: string | RgbColor,
  level: ContrastLevel = "AA",
  textSize: ContrastTextSize = "normal",
): boolean => {
  const ratio = getContrastRatio(foreground, background);
  return ratio >= WCAG_THRESHOLDS[level][textSize];
};

/**
 * Pick dark or light text for a given background.
 *
 * Mid-tone brand colors (e.g. #79B485) often give a slightly higher ratio for
 * dark text while still looking better with light text. We therefore prefer
 * light text unless the background is clearly light (high luminance).
 */
export const getPreferredTextColor = (
  background: string | RgbColor,
  options: {
    dark?: string;
    light?: string;
    /** Backgrounds below this luminance always get light text. Default: 0.6 */
    lightBackgroundLuminance?: number;
  } = {},
): string => {
  const dark = options.dark ?? "#000000";
  const light = options.light ?? "#ffffff";
  const lightBackgroundLuminance = options.lightBackgroundLuminance ?? 0.6;
  const luminance = getRelativeLuminance(background);

  if (luminance < lightBackgroundLuminance) {
    return light;
  }

  const darkRatio = getContrastRatio(dark, background);
  const lightRatio = getContrastRatio(light, background);

  return lightRatio >= darkRatio ? light : dark;
};
