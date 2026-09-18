export function labelFor(value, fallback = "Not specified") {
  if (!value) return fallback;
  return String(value)
    .toLowerCase()
    .replaceAll("_", " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
