import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SecretValue from '@/components/SecretValue.vue'

describe('SecretValue', () => {
  it('displays masked value by default', () => {
    const wrapper = mount(SecretValue, {
      props: {
        value: 'super-secret-password-123',
        masked: true
      }
    })
    
    expect(wrapper.text()).toContain('••••')
    expect(wrapper.text()).not.toContain('super-secret')
  })

  it('displays unmasked value when masked is false', () => {
    const wrapper = mount(SecretValue, {
      props: {
        value: 'visible-value',
        masked: false
      }
    })
    
    expect(wrapper.text()).toContain('visible-value')
    expect(wrapper.text()).not.toContain('••••')
  })

  it('toggles masking when eye icon is clicked', async () => {
    const wrapper = mount(SecretValue, {
      props: {
        value: 'toggle-me',
        masked: true,
        allowToggle: true
      }
    })
    
    expect(wrapper.text()).toContain('••••')
    
    const toggleBtn = wrapper.find('button')
    await toggleBtn.trigger('click')
    
    expect(wrapper.emitted()).toHaveProperty('toggle-mask')
    expect(wrapper.emitted()['toggle-mask'][0]).toEqual([])
  })

  it('copies value to clipboard when copy button is clicked', async () => {
    const mockCopy = vi.fn()
    global.navigator.clipboard = { writeText: mockCopy }
    
    const wrapper = mount(SecretValue, {
      props: {
        value: 'copy-me',
        masked: false,
        allowCopy: true
      }
    })
    
    const copyBtn = wrapper.findAll('button').find(btn => 
      btn.html().includes('Clipboard')
    )
    await copyBtn.trigger('click')
    
    expect(mockCopy).toHaveBeenCalledWith('copy-me')
    expect(wrapper.emitted()).toHaveProperty('copy')
  })

  it('handles null value gracefully', () => {
    const wrapper = mount(SecretValue, {
      props: {
        value: null,
        masked: false
      }
    })
    
    expect(wrapper.text()).toContain('(null)')
  })

  it('masks long values with character count', () => {
    const longValue = 'a'.repeat(30)
    const wrapper = mount(SecretValue, {
      props: {
        value: longValue,
        masked: true
      }
    })
    
    const text = wrapper.text()
    expect(text).toContain('aa••••aa')
  })
})