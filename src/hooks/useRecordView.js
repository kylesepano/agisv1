import { useEffect } from "react";
import { viewActivityApi } from "../services/api";

export default function useRecordView(record, options) {
  const id = record?.id;
  const code = id ? options.code?.(record) : "";
  const label = id ? options.label?.(record) : "";

  useEffect(() => {
    if (!id || !label) return;

    // This is intentionally fire-and-forget: logging must not block an
    // authorized detail modal. The API performs permission checks and deduping.
    viewActivityApi
      .record({
        module: options.module,
        recordType: options.recordType,
        recordId: id,
        recordCode: code || null,
        recordLabel: label,
        route: window.location.pathname + window.location.search,
      })
      .catch(() => {
        // View logging must never prevent an authorized detail screen from opening.
      });
  }, [code, id, label, options.module, options.recordType]);
}
