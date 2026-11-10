import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type InputHTMLAttributes,
  type KeyboardEvent,
} from "react";
import { Check, ChevronDown } from "lucide-react";
import { Input } from "../input";
import { cn } from "../../../utils/helpers";

export interface ComboboxOption {
  value: string;
  label: string;
}

type ComboboxProps = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  "value" | "onChange"
> & {
  value?: string;
  options: ComboboxOption[];
  onChange?: (value: string) => void;
  placeholder?: string;
  emptyMessage?: string;
};

export function Combobox({
  value,
  options,
  onChange,
  placeholder = "Search...",
  emptyMessage = "No results found.",
  className,
  disabled = false,
  ...props
}: ComboboxProps) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  const selected = options.find((option) => option.value === value);

  const filteredOptions = useMemo(() => {
    const searchValue = query.trim().toLowerCase();

    if (!searchValue) {
      return options;
    }

    return options.filter((option) =>
      option.label.toLowerCase().includes(searchValue),
    );
  }, [options, query]);

  const closeDropdown = () => {
    setOpen(false);
    setQuery("");
    setHighlightedIndex(0);
  };

  const commitSelection = (option: ComboboxOption) => {
    onChange?.(option.value);
    closeDropdown();
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

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (disabled) {
      return;
    }

    if (!open && event.key === "ArrowDown") {
      event.preventDefault();
      setOpen(true);
      setHighlightedIndex(0);
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

      if (option) {
        commitSelection(option);
      }
    }
  };

  const inputValue = open ? query : selected?.label ?? "";

  return (
    <div ref={containerRef} className="relative">
      <Input
        {...props}
        role="combobox"
        aria-expanded={open}
        disabled={disabled}
        className={cn("h-9 w-full rounded-lg pr-10", className)}
        placeholder={placeholder}
        value={inputValue}
        onFocus={() => {
          if (disabled) {
            return;
          }

          setOpen(true);
          setQuery("");
          setHighlightedIndex(0);
        }}
        onChange={(event) => {
          if (!open) {
            setOpen(true);
          }

          setQuery(event.target.value);
          setHighlightedIndex(0);
        }}
        onKeyDown={handleKeyDown}
      />
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />

      {open ? (
        <div className="absolute left-0 right-0 top-[calc(100%+0.35rem)] z-50 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
          <div className="max-h-60 overflow-y-auto p-1.5">
            {filteredOptions.length === 0 ? (
              <div className="px-3 py-4 text-sm text-slate-500">
                {emptyMessage}
              </div>
            ) : (
              filteredOptions.map((option, index) => (
                <button
                  key={option.value}
                  type="button"
                  className={cn(
                    "flex w-full items-center rounded-lg px-3 py-2 text-left text-sm text-slate-700 transition",
                    index === highlightedIndex && "bg-slate-100",
                    value === option.value && "text-slate-950",
                  )}
                  onClick={() => commitSelection(option)}
                  onMouseEnter={() => setHighlightedIndex(index)}
                >
                  <Check
                    className={cn(
                      "mr-2 h-4 w-4",
                      value === option.value ? "opacity-100" : "opacity-0",
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
