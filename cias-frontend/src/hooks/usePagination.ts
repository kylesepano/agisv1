export function usePagination<T>(items: T[], pageSize = 10) {
  return {
    currentPage: 1,
    pageSize,
    total: items.length,
    data: items.slice(0, pageSize),
  }
}
