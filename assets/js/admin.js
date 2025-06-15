/**
 * PriceTuneX Admin JavaScript - Complete Enhanced Version
 */
(function($) {
    'use strict';

    var PriceTuneXAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initFormDependencies();
            this.loadLogs();
            this.updateStatistics();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.handleTabClick.bind(this));
            
            // Form dependencies
            $(document).on('change', '#rule_type', this.handleRuleTypeChange.bind(this));
            $(document).on('change', '#target_scope', this.handleTargetScopeChange.bind(this));
            $(document).on('change', '#apply_rounding', this.handleRoundingToggle.bind(this));
            
            // Button clicks
            $(document).on('click', '#preview-rules', this.handlePreviewRules.bind(this));
            $(document).on('click', '#apply-rules', this.handleApplyRules.bind(this));
            $(document).on('click', '#undo-last', this.handleUndoLast.bind(this));
            $(document).on('click', '#refresh-logs', this.handleRefreshLogs.bind(this));
            $(document).on('click', '#clear-logs', this.handleClearLogs.bind(this));
            
            // Modal events
            $(document).on('click', '.modal-close, #modal-cancel', this.hideModal.bind(this));
            $(document).on('click', '#modal-confirm', this.handleModalConfirm.bind(this));
            
            // Close modal when clicking outside
            $(document).on('click', '.pricetunex-modal', function(e) {
                if (e.target === this) {
                    PriceTuneXAdmin.hideModal();
                }
            });
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            // Show first tab by default
            $('.tab-content').hide();
            $('.tab-content.active').show();
            
            // Activate first tab
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[data-tab="price-rules"]').addClass('nav-tab-active');
        },

        /**
         * Handle tab clicks
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $tab = $(e.currentTarget);
            var targetTab = $tab.data('tab');
            
            // Update tab states
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').removeClass('active').hide();
            $('#' + targetTab).addClass('active').fadeIn(300);
            
            // Load logs if switching to logs tab
            if (targetTab === 'logs') {
                this.loadLogs();
            }
        },

        /**
         * Initialize form dependencies
         */
        initFormDependencies: function() {
            this.handleRuleTypeChange();
            this.handleTargetScopeChange();
            this.handleRoundingToggle();
        },

        /**
         * Handle rule type change
         */
        handleRuleTypeChange: function() {
            var ruleType = $('#rule_type').val();
            
            if (ruleType === 'percentage') {
                $('.percentage-desc').show();
                $('.fixed-desc').hide();
            } else {
                $('.percentage-desc').hide();
                $('.fixed-desc').show();
            }
        },

        /**
         * Handle target scope change
         */
        handleTargetScopeChange: function() {
            var scope = $('#target_scope').val();
            
            // Hide all scope fields
            $('.scope-field').hide();
            
            // Show relevant field
            if (scope !== 'all') {
                $('#' + scope + '-field').show();
            }
        },

        /**
         * Handle rounding toggle
         */
        handleRoundingToggle: function() {
            var isChecked = $('#apply_rounding').is(':checked');
            
            if (isChecked) {
                $('#rounding-options').show();
            } else {
                $('#rounding-options').hide();
            }
        },

        /**
         * Handle preview rules
         */
        handlePreviewRules: function(e) {
            e.preventDefault();
            
            var formData = this.getFormData();
            if (!this.validateForm(formData)) {
                return;
            }
            
            this.setButtonLoading('preview-rules', true);
            this.showLoading(pricetunex_ajax.strings.processing);
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_preview_rules',
                    nonce: pricetunex_ajax.nonce,
                    ...formData
                },
                success: this.handlePreviewSuccess.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('preview-rules', false);
                    PriceTuneXAdmin.hideLoading();
                }
            });
        },

        /**
         * Handle apply rules
         */
        handleApplyRules: function(e) {
            e.preventDefault();
            
            var formData = this.getFormData();
            if (!this.validateForm(formData)) {
                return;
            }
            
            this.showModal(
                pricetunex_ajax.strings.confirm_apply,
                'apply-rules'
            );
        },

        /**
         * Handle undo last changes
         */
        handleUndoLast: function(e) {
            e.preventDefault();
            
            this.showModal(
                pricetunex_ajax.strings.confirm_undo,
                'undo-changes'
            );
        },

        /**
         * Handle refresh logs
         */
        handleRefreshLogs: function(e) {
            e.preventDefault();
            this.loadLogs();
        },

        /**
         * Handle clear logs
         */
        handleClearLogs: function(e) {
            e.preventDefault();
            
            this.showModal(
                'Are you sure you want to clear all activity logs? This action cannot be undone.',
                'clear-logs'
            );
        },

        /**
         * Handle modal confirmation
         */
        handleModalConfirm: function() {
            var action = $('#pricetunex-modal').data('action');
            
            this.hideModal();
            
            switch (action) {
                case 'apply-rules':
                    this.executeApplyRules();
                    break;
                case 'undo-changes':
                    this.executeUndoChanges();
                    break;
                case 'clear-logs':
                    this.executeClearLogs();
                    break;
            }
        },

        /**
         * Execute apply rules
         */
        executeApplyRules: function() {
            var formData = this.getFormData();
            
            this.setButtonLoading('apply-rules', true);
            this.showLoading(pricetunex_ajax.strings.processing);
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_apply_rules',
                    nonce: pricetunex_ajax.nonce,
                    ...formData
                },
                success: this.handleApplySuccess.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('apply-rules', false);
                    PriceTuneXAdmin.hideLoading();
                }
            });
        },

        /**
         * Execute undo changes
         */
        executeUndoChanges: function() {
            this.setButtonLoading('undo-last', true);
            this.showLoading(pricetunex_ajax.strings.processing);
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_undo_changes',
                    nonce: pricetunex_ajax.nonce
                },
                success: this.handleUndoSuccess.bind(this),
                error: this.handleAjaxError.bind(this),
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('undo-last', false);
                    PriceTuneXAdmin.hideLoading();
                }
            });
        },

        /**
         * Execute clear logs
         */
        executeClearLogs: function() {
            this.setButtonLoading('clear-logs', true);
            this.showLoading('Clearing logs...');
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_clear_logs',
                    nonce: pricetunex_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PriceTuneXAdmin.showMessage('Logs cleared successfully.', 'success');
                        PriceTuneXAdmin.loadLogs();
                    } else {
                        PriceTuneXAdmin.showMessage(response.data.message || 'Failed to clear logs.', 'error');
                    }
                },
                error: function() {
                    PriceTuneXAdmin.showMessage('An error occurred while clearing logs.', 'error');
                },
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('clear-logs', false);
                    PriceTuneXAdmin.hideLoading();
                }
            });
        },

        /**
         * Get form data
         */
        getFormData: function() {
            var data = {};
            
            // Basic fields
            data.rule_type = $('#rule_type').val();
            data.rule_value = parseFloat($('#rule_value').val()) || 0;
            data.target_scope = $('#target_scope').val();
            
            // Scope-specific fields
            data.categories = $('#categories').val() || [];
            data.tags = $('#tags').val() || [];
            data.product_types = [];
            $('input[name="product_types[]"]:checked').each(function() {
                data.product_types.push($(this).val());
            });
            
            data.price_min = parseFloat($('#price_min').val()) || 0;
            data.price_max = parseFloat($('#price_max').val()) || 0;
            
            // Rounding options
            data.apply_rounding = $('#apply_rounding').is(':checked');
            data.rounding_type = $('#rounding_type').val();
            
            return data;
        },

        /**
         * Enhanced form validation with better UX
         */
        validateForm: function(data) {
            // Clear previous error highlights
            $('.form-group').removeClass('error');
            
            // Check if rule value is provided
            if (!data.rule_value || data.rule_value === 0) {
                this.showMessage('Please enter a valid adjustment value.', 'error');
                $('#rule_value').closest('.form-group').addClass('error');
                $('#rule_value').focus();
                return false;
            }
            
            // Validate percentage range
            if (data.rule_type === 'percentage') {
                if (data.rule_value < -100 || data.rule_value > 1000) {
                    this.showMessage('Percentage must be between -100% and 1000%.', 'error');
                    $('#rule_value').closest('.form-group').addClass('error');
                    $('#rule_value').focus();
                    return false;
                }
            }
            
            // Check scope-specific validations
            if (data.target_scope === 'categories' && data.categories.length === 0) {
                this.showMessage('Please select at least one category.', 'error');
                $('#categories').closest('.form-group').addClass('error');
                $('#categories').focus();
                return false;
            }
            
            if (data.target_scope === 'tags' && data.tags.length === 0) {
                this.showMessage('Please select at least one tag.', 'error');
                $('#tags').closest('.form-group').addClass('error');
                $('#tags').focus();
                return false;
            }
            
            if (data.target_scope === 'product_types' && data.product_types.length === 0) {
                this.showMessage('Please select at least one product type.', 'error');
                $('input[name="product_types[]"]').first().closest('.form-group').addClass('error');
                return false;
            }
            
            if (data.target_scope === 'price_range' && data.price_min === 0 && data.price_max === 0) {
                this.showMessage('Please specify a price range.', 'error');
                $('#price_min').closest('.form-group').addClass('error');
                $('#price_min').focus();
                return false;
            }
            
            // Validate price range logic
            if (data.target_scope === 'price_range' && data.price_min > 0 && data.price_max > 0) {
                if (data.price_min >= data.price_max) {
                    this.showMessage('Maximum price must be greater than minimum price.', 'error');
                    $('#price_max').closest('.form-group').addClass('error');
                    $('#price_max').focus();
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Handle preview success
         */
        handlePreviewSuccess: function(response) {
            if (response.success) {
                this.displayPreview(response.data);
                this.showMessage('Preview generated successfully.', 'success');
            } else {
                this.showMessage(response.data.message || pricetunex_ajax.strings.error, 'error');
            }
        },

        /**
         * Handle apply success
         */
        handleApplySuccess: function(response) {
            if (response.success) {
                this.clearPreview();
                this.showMessage(
                    `Successfully updated ${response.data.products_updated} products.`,
                    'success'
                );
                this.updateStatistics();
                this.loadLogs();
                this.resetForm();
            } else {
                this.showMessage(response.data.message || pricetunex_ajax.strings.error, 'error');
            }
        },

        /**
         * Handle undo success
         */
        handleUndoSuccess: function(response) {
            if (response.success) {
                this.showMessage(
                    `Successfully restored ${response.data.products_restored} products.`,
                    'success'
                );
                this.updateStatistics();
                this.loadLogs();
            } else {
                this.showMessage(response.data.message || 'No changes to undo.', 'error');
            }
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            this.showMessage('An error occurred. Please try again.', 'error');
        },

        /**
         * Display preview results
         */
        displayPreview: function(data) {
            var template = wp.template('preview-template');
            var html = template(data);
            
            $('#preview-results').html(html).addClass('fade-in');
        },

        /**
         * Clear preview
         */
        clearPreview: function() {
            $('#preview-results').html('<p class="no-preview">Click "Preview Changes" to see affected products.</p>');
        },

        /**
         * Load activity logs
         */
        loadLogs: function() {
            this.setButtonLoading('refresh-logs', true);
            $('#logs-container').html('<div class="logs-loading"><span class="spinner is-active"></span><p>Loading logs...</p></div>');
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_get_logs',
                    nonce: pricetunex_ajax.nonce
                },
                success: this.handleLogsSuccess.bind(this),
                error: function() {
                    $('#logs-container').html('<p class="text-center">Error loading logs.</p>');
                },
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('refresh-logs', false);
                }
            });
        },

        /**
         * Handle logs success
         */
        handleLogsSuccess: function(response) {
            if (response.success && response.data.logs) {
                this.displayLogs(response.data.logs);
            } else {
                $('#logs-container').html('<p class="text-center">No logs found.</p>');
            }
        },

        /**
         * Display logs
         */
        displayLogs: function(logs) {
            var html = '';
            var template = wp.template('log-template');
            
            if (logs.length === 0) {
                html = '<p class="text-center">No activity logs found.</p>';
            } else {
                logs.forEach(function(log) {
                    html += template(log);
                });
            }
            
            $('#logs-container').html(html);
        },

        /**
         * Enhanced update statistics with proper AJAX call
         */
        updateStatistics: function() {
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_get_stats',
                    nonce: pricetunex_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#total-products').text(response.data.total_products || '-');
                        if (response.data.last_update) {
                            $('#last-update').text(response.data.last_update);
                        }
                        
                        // Update additional stats if elements exist
                        if (response.data.stats) {
                            var stats = response.data.stats;
                            $('.stat-simple-products').text(stats.simple_products || 0);
                            $('.stat-variable-products').text(stats.variable_products || 0);
                            $('.stat-products-with-price').text(stats.products_with_price || 0);
                            $('.stat-average-price').text(stats.average_price ? '$' + stats.average_price.toFixed(2) : '-');
                        }
                    }
                },
                error: function() {
                    // Silently fail for stats - non-critical functionality
                    console.log('Failed to load statistics');
                }
            });
        },

        /**
         * Add loading states to buttons
         */
        setButtonLoading: function(buttonId, loading) {
            var $button = $('#' + buttonId);
            if (loading) {
                $button.prop('disabled', true);
                $button.find('.dashicons').addClass('spin');
                $button.data('original-text', $button.text());
                $button.append(' <span class="spinner is-active" style="float: none; margin: 0 0 0 5px;"></span>');
            } else {
                $button.prop('disabled', false);
                $button.find('.dashicons').removeClass('spin');
                $button.find('.spinner').remove();
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            $('#loading-message').text(message || pricetunex_ajax.strings.processing);
            $('#pricetunex-loading').fadeIn(200);
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#pricetunex-loading').fadeOut(200);
        },

        /**
         * Show modal
         */
        showModal: function(message, action) {
            $('#modal-message').text(message);
            $('#pricetunex-modal').data('action', action).fadeIn(200);
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            $('#pricetunex-modal').fadeOut(200);
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            // Remove existing messages
            $('.pricetunex-message').remove();
            
            // Create new message
            var $message = $('<div class="pricetunex-message ' + type + '">' + message + '</div>');
            
            // Insert after the main heading
            $('.pricetunex-admin h1').after($message);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to top to show message
            $('html, body').animate({
                scrollTop: $('.pricetunex-admin').offset().top - 50
            }, 300);
        },

        /**
         * Reset form
         */
        resetForm: function() {
            $('#pricetunex-rules-form')[0].reset();
            this.initFormDependencies();
            this.clearPreview();
            
            // Clear any error states
            $('.form-group').removeClass('error');
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on our admin page
        if ($('.pricetunex-admin').length) {
            PriceTuneXAdmin.init();
        }
    });

    /**
     * Handle escape key to close modals
     */
    $(document).keyup(function(e) {
        if (e.keyCode === 27) { // Escape key
            PriceTuneXAdmin.hideModal();
        }
    });

    /**
     * Prevent form submission on enter in number inputs
     */
    $(document).on('keypress', 'input[type="number"]', function(e) {
        if (e.which === 13) {
            e.preventDefault();
        }
    });

    /**
     * Auto-update preview when form changes (debounced)
     */
    var previewTimeout;
    $(document).on('change input', '#pricetunex-rules-form input, #pricetunex-rules-form select', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(function() {
            // Only auto-preview if we have a rule value
            if ($('#rule_value').val() && parseFloat($('#rule_value').val()) !== 0) {
                $('#preview-rules').trigger('click');
            }
        }, 1000);
    });

    /**
     * Make the admin interface globally accessible for debugging
     */
    window.PriceTuneXAdmin = PriceTuneXAdmin;

})(jQuery);