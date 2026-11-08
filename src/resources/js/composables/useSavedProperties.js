import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const savedPropertyIds = ref([])

const normalizeSavedIds = (rawIds) => {
  if (!Array.isArray(rawIds)) {
    return []
  }

  const normalized = rawIds
    .map((value) => Number(value))
    .filter((value) => Number.isInteger(value) && value > 0)

  return Array.from(new Set(normalized))
}

const applySavedIds = (rawIds) => {
  savedPropertyIds.value = normalizeSavedIds(rawIds)
}

export const useSavedProperties = () => {
  const page = usePage()
  const isAuthenticated = computed(() => Boolean(page.props.auth?.user))

  watch(
    () => page.props.savedPropertyIds,
    (value) => {
      applySavedIds(value)
    },
    { immediate: true },
  )

  const savedCount = computed(() => savedPropertyIds.value.length)

  const isSaved = (propertyId) => {
    const normalizedPropertyId = Number(propertyId)
    return savedPropertyIds.value.includes(normalizedPropertyId)
  }

  const setSavedState = async (propertyId, shouldSave) => {
    const normalizedPropertyId = Number(propertyId)

    if (!Number.isInteger(normalizedPropertyId) || normalizedPropertyId <= 0) {
      return { saved: false, requiresLogin: false, failed: true }
    }

    if (!isAuthenticated.value) {
      return { saved: false, requiresLogin: true, failed: false }
    }

    const wasSaved = isSaved(normalizedPropertyId)

    if (wasSaved === shouldSave) {
      return { saved: wasSaved, requiresLogin: false, failed: false }
    }

    const previousIds = [...savedPropertyIds.value]

    if (shouldSave) {
      savedPropertyIds.value = Array.from(new Set([normalizedPropertyId, ...savedPropertyIds.value]))
    } else {
      savedPropertyIds.value = savedPropertyIds.value.filter((id) => id !== normalizedPropertyId)
    }

    if (typeof window === 'undefined' || !window.axios) {
      return { saved: shouldSave, requiresLogin: false, failed: false }
    }

    try {
      const response = await window.axios.post('/saved/toggle', {
        property_id: normalizedPropertyId,
      })

      if (response?.data?.savedIds) {
        applySavedIds(response.data.savedIds)
      }

      return {
        saved: response?.data?.saved ?? shouldSave,
        requiresLogin: false,
        failed: false,
      }
    } catch (error) {
      savedPropertyIds.value = previousIds
      return { saved: wasSaved, requiresLogin: false, failed: true }
    }
  }

  const toggleSaved = (propertyId) => setSavedState(propertyId, !isSaved(propertyId))
  const upsertSavedProperty = (propertyId) => setSavedState(propertyId, true)
  const removeSavedProperty = (propertyId) => setSavedState(propertyId, false)

  return {
    savedPropertyIds,
    savedCount,
    isSaved,
    toggleSaved,
    upsertSavedProperty,
    removeSavedProperty,
  }
}
