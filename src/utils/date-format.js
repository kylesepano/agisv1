function partMap(date, timezone) {
  const parts = new Intl.DateTimeFormat("en-PH", {
    timeZone: timezone,
    year: "numeric",
    month: "long",
    day: "numeric",
  }).formatToParts(date);

  return Object.fromEntries(parts.map((part) => [part.type, part.value]));
}

export function formatConfiguredDate(
  value,
  {
    format = "MMMM d, yyyy",
    timezone = "Asia/Manila",
    weekday = false,
  } = {},
) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const parts = partMap(date, timezone);
  const numericMonth = new Intl.DateTimeFormat("en-PH", {
    timeZone: timezone,
    month: "2-digit",
  }).format(date);
  const shortMonth = new Intl.DateTimeFormat("en-PH", {
    timeZone: timezone,
    month: "short",
  }).format(date);
  const formatted = format.replace(
    /MMMM|MMM|MM|yyyy|dd|d/g,
    (token) =>
      ({
        MMMM: parts.month,
        MMM: shortMonth,
        MM: numericMonth,
        yyyy: parts.year,
        dd: String(parts.day).padStart(2, "0"),
        d: parts.day,
      })[token],
  );

  if (!weekday) return formatted;
  const dayName = new Intl.DateTimeFormat("en-PH", {
    timeZone: timezone,
    weekday: "long",
  }).format(date);
  return `${dayName}, ${formatted}`;
}
