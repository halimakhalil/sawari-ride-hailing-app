import { useEffect, useRef, useState } from 'react'
import { toApiError, type ApiError } from '../api/client'

/**
 * Runs `task` every `intervalMs` while `enabled`. A failed poll keeps the last
 * good data on screen and reports the error, so a flaky connection shows a
 * "reconnecting" hint instead of wiping the ride status. Overlapping calls are
 * skipped, and the interval is cleared on unmount.
 */
export function usePolling(task: () => Promise<void>, intervalMs: number, enabled: boolean): ApiError | null {
  const [error, setError] = useState<ApiError | null>(null)
  const taskRef = useRef(task)

  useEffect(() => {
    taskRef.current = task
  }, [task])

  useEffect(() => {
    if (!enabled) return

    let inFlight = false
    let cancelled = false

    const tick = async () => {
      if (inFlight) return
      inFlight = true
      try {
        await taskRef.current()
        if (!cancelled) setError(null)
      } catch (err) {
        if (!cancelled) setError(toApiError(err))
      } finally {
        inFlight = false
      }
    }

    const id = window.setInterval(tick, intervalMs)
    return () => {
      cancelled = true
      window.clearInterval(id)
    }
  }, [intervalMs, enabled])

  return enabled ? error : null
}
