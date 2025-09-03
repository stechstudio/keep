import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Toast from '@/components/Toast.vue'

describe('Toast', () => {
  let wrapper

  beforeEach(() => {
    wrapper = mount(Toast)
  })

  it('renders nothing when no toasts', () => {
    expect(wrapper.find('.fixed').exists()).toBe(false)
  })

  it('shows success toast when success method is called', async () => {
    const toast = wrapper.vm
    toast.success('Success!', 'Operation completed')
    
    await nextTick()
    
    const toastEl = wrapper.find('.bg-green-900')
    expect(toastEl.exists()).toBe(true)
    expect(wrapper.text()).toContain('Success!')
    expect(wrapper.text()).toContain('Operation completed')
  })

  it('shows error toast with red background', async () => {
    const toast = wrapper.vm
    toast.error('Error!', 'Something went wrong')
    
    await nextTick()
    
    const toastEl = wrapper.find('.bg-red-900')
    expect(toastEl.exists()).toBe(true)
    expect(wrapper.text()).toContain('Error!')
    expect(wrapper.text()).toContain('Something went wrong')
  })

  it('shows info toast with blue background', async () => {
    const toast = wrapper.vm
    toast.info('Info', 'Just so you know')
    
    await nextTick()
    
    const toastEl = wrapper.find('.bg-blue-900')
    expect(toastEl.exists()).toBe(true)
    expect(wrapper.text()).toContain('Info')
    expect(wrapper.text()).toContain('Just so you know')
  })

  it('removes toast when close button is clicked', async () => {
    const toast = wrapper.vm
    toast.success('Close me')
    
    await nextTick()
    expect(wrapper.find('.bg-green-900').exists()).toBe(true)
    
    const closeBtn = wrapper.find('button')
    await closeBtn.trigger('click')
    
    await nextTick()
    expect(wrapper.find('.bg-green-900').exists()).toBe(false)
  })

  it('auto-removes toast after timeout', async () => {
    vi.useFakeTimers()
    
    const toast = wrapper.vm
    toast.success('Auto remove', '', 1000)
    
    await nextTick()
    expect(wrapper.find('.bg-green-900').exists()).toBe(true)
    
    vi.advanceTimersByTime(1100)
    await nextTick()
    
    expect(wrapper.find('.bg-green-900').exists()).toBe(false)
    
    vi.useRealTimers()
  })

  it('can show multiple toasts', async () => {
    const toast = wrapper.vm
    toast.success('First')
    toast.error('Second')
    toast.info('Third')
    
    await nextTick()
    
    const toasts = wrapper.findAll('[class*="bg-"]')
    expect(toasts).toHaveLength(3)
  })
})