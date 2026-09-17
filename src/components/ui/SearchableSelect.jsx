import { useEffect, useMemo, useRef, useState } from "react";
import { Check, ChevronDown, Plus, Search, X } from "lucide-react";

export default function SearchableSelect({
  options,
  value,
  onChange,
  placeholder = "Select an option",
  searchPlaceholder = "Type to search...",
  multiple = false,
  multipleDisplay = "chips",
  allowCustom = false,
  disabled = false,
  emptyMessage = "No matching options.",
}) {
  const rootRef = useRef(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const selectedValues = multiple
    ? Array.isArray(value)
      ? value
      : []
    : value === null || value === undefined || value === ""
      ? []
      : [value];
  const selectedOptions = selectedValues.map(
    (selectedValue) =>
      options.find(
        (option) => String(option.value) === String(selectedValue),
      ) ?? { value: selectedValue, label: String(selectedValue), custom: true },
  );
  const filteredOptions = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return options;

    return options.filter((option) =>
      [option.label, option.keywords, option.value].some((candidate) =>
        String(candidate ?? "")
          .toLowerCase()
          .includes(normalizedQuery),
      ),
    );
  }, [options, query]);
  const canCreate =
    allowCustom &&
    query.trim() !== "" &&
    !options.some(
      (option) => option.label.toLowerCase() === query.trim().toLowerCase(),
    );

  useEffect(() => {
    function handleOutsideClick(event) {
      if (!rootRef.current?.contains(event.target)) {
        setOpen(false);
        setQuery("");
      }
    }

    function handleEscape(event) {
      if (event.key === "Escape") {
        setOpen(false);
        setQuery("");
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    document.addEventListener("keydown", handleEscape);
    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
      document.removeEventListener("keydown", handleEscape);
    };
  }, []);

  function selectOption(optionValue) {
    if (multiple) {
      const exists = selectedValues.some(
        (selectedValue) => String(selectedValue) === String(optionValue),
      );
      onChange(
        exists
          ? selectedValues.filter(
              (selectedValue) => String(selectedValue) !== String(optionValue),
            )
          : [...selectedValues, optionValue],
      );
      return;
    }

    onChange(optionValue);
    setOpen(false);
    setQuery("");
  }

  function removeOption(optionValue) {
    if (multiple) {
      onChange(
        selectedValues.filter(
          (selectedValue) => String(selectedValue) !== String(optionValue),
        ),
      );
    } else {
      onChange("");
    }
  }

  return (
    <div className="relative" ref={rootRef}>
      <button
        aria-expanded={open}
        className="flex min-h-11 w-full items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm outline-none transition focus:border-sky-500 focus:ring-2 focus:ring-sky-100 disabled:bg-slate-100"
        disabled={disabled}
        onClick={() => setOpen((current) => !current)}
        type="button"
      >
        <span className="flex min-w-0 flex-1 flex-wrap gap-1.5">
          {selectedOptions.length === 0 && (
            <span className="truncate text-slate-400">{placeholder}</span>
          )}
          {multiple &&
            multipleDisplay === "summary" &&
            selectedOptions.length > 0 && (
              <span className="truncate font-semibold text-slate-700">
                {selectedOptions.length}{" "}
                {selectedOptions.length === 1 ? "option" : "options"} selected
              </span>
            )}
          {selectedOptions.map((option) =>
            multiple && multipleDisplay === "summary" ? null : multiple ? (
              <span
                className="inline-flex max-w-full items-center gap-1 rounded-md bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-800"
                key={option.value}
              >
                <span className="truncate">{option.label}</span>
                <span
                  aria-label={`Remove ${option.label}`}
                  className="rounded hover:bg-sky-200"
                  onClick={(event) => {
                    event.stopPropagation();
                    removeOption(option.value);
                  }}
                  role="button"
                  tabIndex={0}
                >
                  <X size={12} />
                </span>
              </span>
            ) : (
              <span className="truncate text-slate-800" key={option.value}>
                {option.label}
              </span>
            ),
          )}
        </span>
        <ChevronDown
          className={`shrink-0 text-slate-400 transition ${open ? "rotate-180" : ""}`}
          size={17}
        />
      </button>

      {open && !disabled && (
        <div className="absolute z-[130] mt-1 w-full min-w-0 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:min-w-64">
          <label className="flex h-11 items-center gap-2 border-b border-slate-200 px-3 text-slate-400">
            <Search size={16} />
            <input
              autoFocus
              className="min-w-0 flex-1 text-sm text-slate-800 outline-none"
              onChange={(event) => setQuery(event.target.value)}
              placeholder={searchPlaceholder}
              value={query}
            />
          </label>
          <div className="max-h-64 overflow-y-auto p-1.5">
            {filteredOptions.map((option) => {
              const selected = selectedValues.some(
                (selectedValue) =>
                  String(selectedValue) === String(option.value),
              );
              return (
                <button
                  className={`flex w-full items-start gap-2 rounded-lg px-3 py-2.5 text-left text-sm transition ${
                    selected
                      ? "bg-sky-50 font-semibold text-sky-800"
                      : "text-slate-700 hover:bg-slate-50"
                  }`}
                  key={option.value}
                  onClick={() => selectOption(option.value)}
                  type="button"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate">{option.label}</span>
                    {option.description && (
                      <small className="mt-0.5 block truncate text-[11px] font-normal text-slate-500">
                        {option.description}
                      </small>
                    )}
                  </span>
                  {selected && <Check className="shrink-0" size={16} />}
                </button>
              );
            })}
            {canCreate && (
              <button
                className="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                onClick={() => selectOption(query.trim())}
                type="button"
              >
                <Plus size={16} />
                Use “{query.trim()}” and add it to Positions
              </button>
            )}
            {filteredOptions.length === 0 && !canCreate && (
              <p className="px-3 py-6 text-center text-sm text-slate-500">
                {emptyMessage}
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
