import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'
import { describe, expect, it } from 'vitest'
import { http, toApiError } from './client'

function responseError(status: number, data: unknown): AxiosError {
  const response = { status, data, statusText: '', headers: {}, config: { headers: new AxiosHeaders() } } as AxiosResponse
  return new AxiosError('Request failed', 'ERR_BAD_RESPONSE', undefined, undefined, response)
}

describe('toApiError', () => {
  it('reads the server error envelope', () => {
    const error = toApiError(
      responseError(409, { error: { code: 'RIDE_ALREADY_ASSIGNED', message: 'Another driver has already accepted this ride.', details: {}, request_id: 'abc-123' } }),
    )

    expect(error.status).toBe(409)
    expect(error.code).toBe('RIDE_ALREADY_ASSIGNED')
    expect(error.message).toBe('Another driver has already accepted this ride.')
    expect(error.requestId).toBe('abc-123')
    expect(error.isNetworkError).toBe(false)
  })

  it('exposes validation messages per field', () => {
    const error = toApiError(
      responseError(422, { error: { code: 'VALIDATION_FAILED', message: 'Invalid', details: { category: ['The selected category is invalid.'] } } }),
    )

    expect(error.fieldErrors).toEqual({ category: 'The selected category is invalid.' })
  })

  it('treats a missing response as a network error', () => {
    const error = toApiError(new AxiosError('Network Error', 'ERR_NETWORK'))

    expect(error.isNetworkError).toBe(true)
    expect(error.code).toBe('NETWORK_ERROR')
  })

  it('rejects a successful status with a non-JSON body', async () => {
    const adapter = async () => ({ data: '<b>Fatal error</b>', status: 200, statusText: 'OK', headers: {}, config: { headers: new AxiosHeaders() } })

    await expect(http.get('/anything', { adapter })).rejects.toMatchObject({ code: 'INVALID_RESPONSE', status: 200 })
  })

  it('handles non-JSON error responses', () => {
    const error = toApiError(responseError(502, '<html>Bad gateway</html>'))

    expect(error.status).toBe(502)
    expect(error.code).toBe('HTTP_ERROR')
  })
})
