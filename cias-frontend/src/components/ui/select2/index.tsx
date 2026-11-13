import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import { Check, ChevronsUpDown, Search, X } from "lucide-react";

import { Button } from "../button";
import { Input } from "../input";
import { cn } from "../../../utils/helpers";

export interface Select2Option {
  value: string;
  label: string;
  disabled?: boolean;
}

interface Select2Props {
  value?: string;
  placeholder?: string;
  options: Select2Option[];
  onChange?: (value: string) => void;
  searchPlaceholder?: string;
  emptyMessage?: string;
  disabled?: boolean;
  searchable?: boolean;
  allowClear?: boolean;
  ariaLabel?: string;
  className?: string;
}

export function Select2({
  value,
  placeholder = "Select option...",
  options,
  onChange,
  searchPlaceholder = "Search...",
  emptyMessage = "No results found.",
  disabled = false,
  searchable = true,
  allowClear = false,
  ariaLabel,
  className,
}: Select2Props) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  const selected = options.find((option) => option.value === value);

  const filteredOptions = useMemo(() => {
    const searchValue = search.trim().toLowerCase();

    if (!searchValue) {
      return options;
    }

    return options.filter((option) =>
      option.label.toLowerCase().includes(searchValue),
    );
  }, [options, search]);

  const openDropdown = () => {
    if (disabled) return;
    setHighlightedIndex(0);
    setOpen(true);
  };

  const closeDropdown = () => {
    setOpen(false);
    setSearch("");
    setHighlightedIndex(0);
  };

  useEffect(() => {
    if (!open) {
      return;
    }

    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        closeDropdown();
      }
    };

    window.addEventListener("mousedown", handlePointerDown);

    return () => {
      window.removeEventListener("mousedown", handlePointerDown);
    };
  }, [open]);

  const commitSelection = (option: Select2Option) => {
    if (option.disabled) return;
    onChange?.(option.value);
    closeDropdown();
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (disabled) {
      return;
    }

    if (
      !open &&
      (event.key === "Enter" || event.key === " " || event.key === "ArrowDown")
    ) {
      event.preventDefault();
      openDropdown();
      return;
    }

    if (!open) {
      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      closeDropdown();
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setHighlightedIndex((current) =>
        Math.min(current + 1, Math.max(filteredOptions.length - 1, 0)),
      );
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setHighlightedIndex((current) => Math.max(current - 1, 0));
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();
      const option = filteredOptions[highlightedIndex];

      if (option && !option.disabled) {
        commitSelection(option);
      }
    }
  };

  return (
    <div ref={containerRef} className="relative w-full" onKeyDown={handleKeyDown}>
      <Button
        type="button"
        variant="outline"
        role="combobox"
        aria-label={ariaLabel}
        aria-expanded={open}
        disabled={disabled}
        className={cn(
          "h-10 w-full justify-between rounded-xl border-slate-300 bg-white px-3 text-xs font-normal text-slate-800 shadow-2xs hover:bg-slate-50/80 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500",
          className,
        )}
        onClick={() => {
          if (open) {
            closeDropdown();
            return;
          }

          openDropdown();
        }}
      >
        <span
          className={cn(
            "truncate text-left",
            selected
              ? disabled
                ? "text-slate-500"
                : "text-slate-900"
              : "text-slate-400",
          )}
        >
          {selected?.label ?? placeholder}
        </span>

        <span className="ml-2 flex shrink-0 items-center gap-1">
          {allowClear && selected && !disabled && (
            <span
              role="button"
              tabIndex={-1}
              aria-label={`Clear ${ariaLabel || "selection"}`}
              onClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                onChange?.("");
              }}
              className="rounded p-0.5 text-slate-400 hover:bg-slate-200/70 hover:text-slate-700"
            >
              <X className="h-3.5 w-3.5" />
            </span>
          )}
          <ChevronsUpDown className="h-3.5 w-3.5 opacity-50" />
        </span>
      </Button>

      {open ? (
        <div className="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-50 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
          {searchable && (
            <div className="border-b border-slate-100 p-2">
              <div className="relative">
                <Input
                  autoFocus
                  className="h-8 pr-8 text-xs"
                  placeholder={searchPlaceholder}
                  value={search}
                  onChange={(event) => {
                    setSearch(event.target.value);
                    setHighlightedIndex(0);
                  }}
                />
                <Search className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              </div>
            </div>
          )}

          <div className="max-h-56 overflow-y-auto p-1">
            {filteredOptions.length === 0 ? (
              <div className="px-3 py-3 text-xs text-slate-500">
                {emptyMessage}
              </div>
            ) : (
              filteredOptions.map((option, index) => (
                <button
                  key={option.value}
                  type="button"
                  disabled={option.disabled}
                  className={cn(
                    "flex w-full items-center rounded-md px-2.5 py-1.5 text-left text-xs text-slate-700 transition select-none",
                    option.disabled
                      ? "cursor-not-allowed opacity-45"
                      : "cursor-pointer hover:bg-blue-50/70",
                    index === highlightedIndex && !option.disabled && "bg-slate-100",
                    value === option.value && "bg-blue-50 font-medium text-blue-900",
                  )}
                  onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    commitSelection(option);
                  }}
                  onMouseEnter={() => setHighlightedIndex(index)}
                >
                  <Check
                    className={cn(
                      "mr-2 h-3.5 w-3.5 shrink-0",
                      value === option.value
                        ? "opacity-100 text-blue-600"
                        : "opacity-0",
                    )}
                  />
                  <span className="truncate">{option.label}</span>
                </button>
              ))
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
}

interface Select2MultiProps {
  selected: string[];
  options: Select2Option[];
  onChange: (selected: string[]) => void;
  placeholder?: string;
  searchPlaceholder?: string;
  emptyMessage?: string;
  disabled?: boolean;
  ariaLabel?: string;
  className?: string;
}

export function Select2Multi({
  selected,
  options,
  onChange,
  placeholder = "Select options...",
  searchPlaceholder = "Search...",
  emptyMessage = "No matching options.",
  disabled = false,
  ariaLabel,
  className,
}: Select2MultiProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const containerRef = useRef<HTMLDivElement>(null);

  const labelMap = useMemo(() => {
    const map = new Map<string, string>();
    options.forEach((opt) => map.set(opt.value, opt.label));
    return map;
  }, [options]);

  const filteredOptions = useMemo(() => {
    const searchValue = search.trim().toLowerCase();
    if (!searchValue) return options;
    return options.filter((opt) =>
      opt.label.toLowerCase().includes(searchValue),
    );
  }, [options, search]);

  useEffect(() => {
    if (!open) return;

    const handlePointerDown = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false);
        setSearch("");
      }
    };

    const handleKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === "Escape") {
        setOpen(false);
        setSearch("");
      }
    };

    window.addEventListener("mousedown", handlePointerDown);
    window.addEventListener("keydown", handleKeyDown);
    return () => {
      window.removeEventListener("mousedown", handlePointerDown);
      window.removeEventListener("keydown", handleKeyDown);
    };
  }, [open]);

  const toggleOption = (value: string) => {
    if (disabled) return;
    onChange(
      selected.includes(value)
        ? selected.filter((item) => item !== value)
        : [...selected, value],
    );
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div
        role="combobox"
        aria-label={ariaLabel}
        aria-expanded={open}
        onClick={() => {
          if (!disabled) setOpen((prev) => !prev);
        }}
        className={cn(
          "flex min-h-8 w-full flex-wrap items-center gap-1 rounded border border-[#a9c8dc] px-1.5 py-1 shadow-sm transition focus-within:border-blue-500",
          disabled
            ? "cursor-not-allowed bg-slate-100"
            : "cursor-pointer bg-white hover:border-blue-400",
          className,
        )}
      >
        {selected.length === 0 && (
          <span className="px-1 text-xs text-slate-400">{placeholder}</span>
        )}
        {selected.map((val) => (
          <span
            key={val}
            className="inline-flex max-w-full items-center gap-1 rounded-sm border border-[#c5dced] bg-[#e4f2fc] px-1.5 py-0.5 text-[11px] leading-tight text-slate-700"
          >
            <span className="truncate">{labelMap.get(val) || val}</span>
            {!disabled && (
              <button
                type="button"
                aria-label={`Remove ${labelMap.get(val) || val}`}
                onClick={(event) => {
                  event.stopPropagation();
                  toggleOption(val);
                }}
                className="shrink-0 text-slate-500 hover:text-red-600"
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </span>
        ))}
        {!disabled && (
          <button
            type="button"
            aria-label={ariaLabel}
            aria-expanded={open}
            onClick={(event) => {
              event.stopPropagation();
              setOpen((prev) => !prev);
            }}
            className="ml-auto grid h-6 w-6 shrink-0 place-items-center rounded text-slate-500 hover:bg-slate-100"
          >
            <ChevronsUpDown className="h-3.5 w-3.5 opacity-60" />
          </button>
        )}
      </div>

      {open && !disabled && (
        <div className="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-50 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
          <div className="border-b border-slate-100 p-2">
            <div className="relative">
              <Input
                autoFocus
                className="h-8 pr-8 text-xs"
                placeholder={searchPlaceholder}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
              <Search className="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            </div>
          </div>

          <div className="max-h-56 overflow-y-auto p-1">
            {filteredOptions.length === 0 ? (
              <div className="px-3 py-3 text-xs text-slate-500">
                {emptyMessage}
              </div>
            ) : (
              filteredOptions.map((option) => {
                const isChecked = selected.includes(option.value);
                const isOptionDisabled = option.disabled && !isChecked;
                return (
                  <button
                    key={option.value}
                    type="button"
                    disabled={isOptionDisabled}
                    aria-pressed={isChecked}
                    onClick={() => toggleOption(option.value)}
                    className={cn(
                      "flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-xs transition",
                      isOptionDisabled
                        ? "cursor-not-allowed opacity-45"
                        : "cursor-pointer hover:bg-blue-50",
                      isChecked ? "bg-blue-50/60 font-medium text-blue-900" : "text-slate-700",
                    )}
                  >
                    <span className="flex min-w-0 items-center gap-2">
                      <Check
                        className={cn(
                          "h-3.5 w-3.5 shrink-0",
                          isChecked ? "opacity-100 text-blue-600" : "opacity-0",
                        )}
                      />
                      <span className="truncate">{option.label}</span>
                    </span>
                  </button>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}
