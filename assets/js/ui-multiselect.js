/**
 * UI MultiSelect Dropdown - Premium Enterprise Component
 * Extiende el sistema ui-dropdown existente con selección múltiple
 * Diseño DRY y reutilizable para futuros componentes (ribbons, etc.)
 */

class UIMultiSelect {
  constructor(config) {
    this.config = {
      container: null,
      id: null,
      placeholder: 'Seleccionar opciones...',
      options: [],
      values: [], // valores seleccionados
      maxVisible: 3, // máximo de tags visibles antes de mostrar "+N más"
      searchable: false,
      onChange: null, // callback cuando cambia la selección
      ...config
    };

    this.isOpen = false;
    this.selectedOptions = new Map(); // Map para O(1) lookup

    this.init();
  }

  init() {
    const container = document.getElementById(this.config.container);
    if (!container) {
      console.error(`UIMultiSelect: Container "${this.config.container}" not found`);
      return;
    }

    this.container = container;
    this.render();
    this.bindEvents();
    this.updateSelectedFromConfig();
  }

  render() {
    this.container.innerHTML = `
            <div class="ui-multiselect" id="${this.config.id || 'multiselect-' + Date.now()}">
                <div class="ui-multiselect-trigger">
                    <div class="trigger-content">
                        <span class="trigger-placeholder">${this.config.placeholder}</span>
                        <div class="selected-tags" style="display: none;"></div>
                    </div>
                    <i class="fas fa-chevron-down chevron"></i>
                </div>
                <div class="ui-multiselect-menu">
                    ${this.config.searchable ? '<div class="ui-multiselect-search"><input type="text" placeholder="Buscar..." autocomplete="off"></div>' : ''}
                    <div class="ui-multiselect-options">
                        ${this.renderOptions()}
                    </div>
                </div>
            </div>
        `;

    this.trigger = this.container.querySelector('.ui-multiselect-trigger');
    this.menu = this.container.querySelector('.ui-multiselect-menu');
    this.optionsContainer = this.container.querySelector('.ui-multiselect-options');
    this.triggerContent = this.container.querySelector('.trigger-content');
    this.placeholder = this.container.querySelector('.trigger-placeholder');
    this.tagsContainer = this.container.querySelector('.selected-tags');
    this.chevron = this.container.querySelector('.chevron');

    if (this.config.searchable) {
      this.searchInput = this.container.querySelector('.ui-multiselect-search input');
    }
  }

  renderOptions() {
    if (this.config.options.length === 0) {
      return '<div class="empty-state">No hay opciones disponibles</div>';
    }

    return this.config.options.map(option => `
            <div class="ui-multiselect-option" data-value="${option.value}">
                <div class="option-checkbox"></div>
                <span class="option-text">${option.label}</span>
            </div>
        `).join('');
  }

  bindEvents() {
    // Toggle dropdown
    this.trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      this.toggle();
    });

    // Option selection
    this.optionsContainer.addEventListener('click', (e) => {
      const option = e.target.closest('.ui-multiselect-option');
      if (option) {
        e.stopPropagation();
        this.toggleOption(option.dataset.value);
      }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target)) {
        this.close();
      }
    });

    // Search functionality
    if (this.searchInput) {
      this.searchInput.addEventListener('input', (e) => {
        this.filterOptions(e.target.value);
      });

      this.searchInput.addEventListener('click', (e) => {
        e.stopPropagation();
      });
    }

    // Keyboard navigation
    this.trigger.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.toggle();
      } else if (e.key === 'Escape') {
        this.close();
      }
    });
  }

  toggle() {
    this.isOpen ? this.close() : this.open();
  }

  open() {
    if (this.isOpen || this.container.classList.contains('disabled')) return;

    // Close other dropdowns
    document.querySelectorAll('.ui-dropdown, .ui-multiselect').forEach(other => {
      if (other !== this.container) {
        other.querySelector('.ui-menu, .ui-multiselect-menu')?.classList.remove('show');
        other.querySelector('.ui-trigger, .ui-multiselect-trigger')?.classList.remove('active');
      }
    });

    this.isOpen = true;
    this.trigger.classList.add('active');
    this.menu.classList.add('show');

    // Focus search input if available
    if (this.searchInput) {
      setTimeout(() => this.searchInput.focus(), 100);
    }

    // Emit event
    this.container.dispatchEvent(new CustomEvent('multiselect:open'));
  }

  close() {
    if (!this.isOpen) return;

    this.isOpen = false;
    this.trigger.classList.remove('active');
    this.menu.classList.remove('show');

    // Clear search
    if (this.searchInput) {
      this.searchInput.value = '';
      this.filterOptions('');
    }

    // Emit event
    this.container.dispatchEvent(new CustomEvent('multiselect:close'));
  }

  toggleOption(value) {
    const option = this.config.options.find(opt => opt.value === value);
    if (!option) return;

    if (this.selectedOptions.has(value)) {
      this.selectedOptions.delete(value);
    } else {
      this.selectedOptions.set(value, option);
    }

    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  selectOption(value) {
    const option = this.config.options.find(opt => opt.value === value);
    if (!option || this.selectedOptions.has(value)) return;

    this.selectedOptions.set(value, option);
    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  deselectOption(value) {
    if (!this.selectedOptions.has(value)) return;

    this.selectedOptions.delete(value);
    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  selectAll() {
    this.config.options.forEach(option => {
      this.selectedOptions.set(option.value, option);
    });
    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  deselectAll() {
    this.selectedOptions.clear();
    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  updateUI() {
    // Update option states
    this.optionsContainer.querySelectorAll('.ui-multiselect-option').forEach(optionEl => {
      const value = optionEl.dataset.value;
      if (this.selectedOptions.has(value)) {
        optionEl.classList.add('selected');
      } else {
        optionEl.classList.remove('selected');
      }
    });
  }

  updateTrigger() {
    const selectedCount = this.selectedOptions.size;

    if (selectedCount === 0) {
      // Show placeholder
      this.placeholder.style.display = 'block';
      this.tagsContainer.style.display = 'none';
      this.triggerContent.classList.remove('has-tags');
    } else {
      // Show tags
      this.placeholder.style.display = 'none';
      this.tagsContainer.style.display = 'flex';
      this.triggerContent.classList.add('has-tags');

      const selectedArray = Array.from(this.selectedOptions.values());
      const visibleTags = selectedArray.slice(0, this.config.maxVisible);
      const hiddenCount = selectedCount - visibleTags.length;

      let tagsHTML = '';

      // Render visible tags
      visibleTags.forEach(option => {
        tagsHTML += `
                    <span class="tag" data-value="${option.value}">
                        ${option.label}
                        <span class="tag-remove" data-value="${option.value}">×</span>
                    </span>
                `;
      });

      // Render "more" indicator
      if (hiddenCount > 0) {
        tagsHTML += `<span class="more-tags">+${hiddenCount}</span>`;
      }

      this.tagsContainer.innerHTML = tagsHTML;

      // Bind tag remove events
      this.tagsContainer.querySelectorAll('.tag-remove').forEach(removeBtn => {
        removeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const value = removeBtn.dataset.value;
          this.deselectOption(value);
        });
      });
    }
  }

  updateSelectedFromConfig() {
    if (this.config.values && Array.isArray(this.config.values)) {
      this.config.values.forEach(value => {
        const option = this.config.options.find(opt => opt.value === value);
        if (option) {
          this.selectedOptions.set(value, option);
        }
      });
      this.updateUI();
      this.updateTrigger();
    }
  }

  filterOptions(searchTerm) {
    const term = searchTerm.toLowerCase().trim();
    const options = this.optionsContainer.querySelectorAll('.ui-multiselect-option');

    options.forEach(option => {
      const text = option.querySelector('.option-text').textContent.toLowerCase();
      const matches = text.includes(term);
      option.style.display = matches ? 'flex' : 'none';
    });

    // Show empty state if no matches
    const visibleOptions = Array.from(options).filter(opt => opt.style.display !== 'none');
    const emptyState = this.optionsContainer.querySelector('.empty-state');

    if (visibleOptions.length === 0 && !emptyState) {
      const emptyDiv = document.createElement('div');
      emptyDiv.className = 'empty-state';
      emptyDiv.textContent = 'No se encontraron resultados';
      this.optionsContainer.appendChild(emptyDiv);
    } else if (visibleOptions.length > 0 && emptyState) {
      emptyState.remove();
    }
  }

  getValues() {
    return Array.from(this.selectedOptions.keys());
  }

  getSelectedOptions() {
    return Array.from(this.selectedOptions.values());
  }

  setValues(values) {
    this.selectedOptions.clear();
    if (Array.isArray(values)) {
      values.forEach(value => {
        const option = this.config.options.find(opt => opt.value === value);
        if (option) {
          this.selectedOptions.set(value, option);
        }
      });
    }
    this.updateUI();
    this.updateTrigger();
    this.emitChange();
  }

  setOptions(options) {
    this.config.options = options;
    this.selectedOptions.clear();
    this.optionsContainer.innerHTML = this.renderOptions();
    this.updateTrigger();
    this.emitChange();
  }

  enable() {
    this.container.classList.remove('disabled');
    this.trigger.style.pointerEvents = 'auto';
  }

  disable() {
    this.container.classList.add('disabled');
    this.trigger.style.pointerEvents = 'none';
    this.close();
  }

  destroy() {
    // Remove event listeners
    this.trigger.removeEventListener('click', this.toggle);
    this.optionsContainer.removeEventListener('click', this.toggleOption);

    // Remove DOM elements
    this.container.innerHTML = '';

    // Clear references
    this.container = null;
    this.trigger = null;
    this.menu = null;
    this.optionsContainer = null;
    this.selectedOptions.clear();
  }

  emitChange() {
    const values = this.getValues();
    const selectedOptions = this.getSelectedOptions();

    // Update hidden input if exists
    const hiddenInput = this.container.querySelector('input[type="hidden"]');
    if (hiddenInput) {
      hiddenInput.value = values.join(',');
    }

    // Call callback
    if (typeof this.config.onChange === 'function') {
      this.config.onChange(values, selectedOptions);
    }

    // Emit event
    this.container.dispatchEvent(new CustomEvent('multiselect:change', {
      detail: { values, selectedOptions }
    }));
  }
}

// Auto-initialization support
window.UIMultiSelect = UIMultiSelect;

// Global function for easy initialization (consistent with existing UI system)
window.initUIMultiSelect = function(config) {
  return new UIMultiSelect(config);
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
  module.exports = UIMultiSelect;
}
