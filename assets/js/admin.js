/**
 * PriceTuneX Admin JavaScript - UPDATED WITH TARGET PRICE TYPE
 */
(function($) {
    'use strict';

    var PriceTuneXAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            console.log('PriceTuneX Admin: Initializing...');
            
            // Check if required objects exist
            if (typeof pricetunex_ajax === 'undefined') {
                console.error('PriceTuneX: pricetunex_ajax object not found');
                return;
            }
            
            this.bindEvents();
            this.initTabs();
            this.initFormDependencies();
            this.loadLogs();
            this.updateStatistics();
            
            console.log('PriceTuneX Admin: Initialization complete');
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.nav-tab', this.handleTabClick.bind(this));
            
            // Form dependencies
            $(document).on('change', '#rule_type', this.handleRuleTypeChange.bind(this));
            $(document).on('change', '#target_price_type', this.handleTargetPriceTypeChange.bind(this));
            $(document).on('change', '#target_scope', this.handleTargetScopeChange.bind(this));
            $(document).on('change', '#apply_rounding', this.handleRoundingToggle.bind(this));
            $(document).on('change', '#rounding_type', this.handleRoundingTypeChange.bind(this));
            
            // Button clicks
            $(document).on('click', '#preview-rules', this.handlePreviewRules.bind(this));
            $(document).on('click', '#apply-rules', this.handleApplyRules.bind(this));
            $(document).on('click', '#undo-last', this.handleUndoLast.bind(this));
            $(document).on('click', '#refresh-logs', this.handleRefreshLogs.bind(this));
            $(document).on('click', '#clear-logs', this.handleClearLogs.bind(this));
            
            // Modal events
            $(document).on('click', '.modal-close, #modal-cancel', this.hideModal.bind(this));
            $(document).on('click', '#modal-confirm', this.handleModalConfirm.bind(this));
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $('.tab-content').hide();
            $('.tab-content.active').show();
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
            $('#' + targetTab).addClass('active').show();
            
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
            this.handleTargetPriceTypeChange(); // NEW
            this.handleTargetScopeChange();
            this.handleRoundingToggle();
            this.handleRoundingTypeChange();
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
         * NEW: Handle target price type change
         */
        handleTargetPriceTypeChange: function() {
            var targetPriceType = $('#target_price_type').val();
            var descriptions = {
                'smart': 'Updates the price customers actually see (sale price if active, otherwise regular price).',
                'regular_only': 'Always updates regular prices only. Keeps existing sale prices intact.',
                'sale_only': 'Only updates products that have active sale prices. Great for flash sales.',
                'both_prices': 'Updates both regular and sale prices by the same amount. Maintains discount relationships.'
            };
            
            $('#target-price-description').text(descriptions[targetPriceType] || descriptions['smart']);
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
                // Also check if custom is selected to show custom field
                this.handleRoundingTypeChange();
            } else {
                $('#rounding-options').hide();
                $('#custom-ending-field').hide();
            }
        },        
        
        /**
         * Handle rounding type change
         */
        handleRoundingTypeChange: function() {
            var roundingType = $('#rounding_type').val();
            
            if (roundingType === 'custom') {
                $('#custom-ending-field').show();
            } else {
                $('#custom-ending-field').hide();
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
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_preview_rules',
                    nonce: pricetunex_ajax.nonce,
                    rule_type: formData.rule_type,
                    rule_value: formData.rule_value,
                    target_price_type: formData.target_price_type, // NEW
                    target_scope: formData.target_scope,
                    categories: formData.categories,
                    tags: formData.tags,
                    product_types: formData.product_types,
                    price_min: formData.price_min,
                    price_max: formData.price_max,
                    apply_rounding: formData.apply_rounding,
                    rounding_type: formData.rounding_type,
                    custom_ending: formData.custom_ending
                },
                success: function(response) {
                    if (response.success) {
                        PriceTuneXAdmin.displayPreview(response.data);
                        PriceTuneXAdmin.showMessage('Preview generated successfully.', 'success');
                    } else {
                        PriceTuneXAdmin.showMessage(response.data || 'Preview failed.', 'error');
                    }
                },
                error: function() {
                    PriceTuneXAdmin.showMessage('An error occurred while generating preview.', 'error');
                },
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('preview-rules', false);
                }
            });
        },

        /**
         * Handle apply rules - USES MODAL
         */
        handleApplyRules: function(e) {
            e.preventDefault();
            
            var formData = this.getFormData();
            if (!this.validateForm(formData)) {
                return;
            }
            
            // Get the current preview count for a better confirmation message
            var previewCount = $('#preview-results .count').text();
            var targetPriceType = formData.target_price_type;
            var priceTypeText = this.getPriceTypeDisplayText(targetPriceType);
            
            var confirmMessage = 'Are you sure you want to apply these price changes to ' + 
                               (previewCount || 'the selected') + ' products using ' + priceTypeText + '? This action cannot be undone without using the undo feature.';
            
            this.showModal(confirmMessage, 'apply-rules');
        },

        /**
         * NEW: Get display text for price type
         */
        getPriceTypeDisplayText: function(targetPriceType) {
            var priceTypeTexts = {
                'smart': 'smart price selection',
                'regular_only': 'regular prices only',
                'sale_only': 'sale prices only',
                'both_prices': 'both regular and sale prices'
            };
            return priceTypeTexts[targetPriceType] || 'smart price selection';
        },

        /**
         * Handle undo last changes - USES MODAL
         */
        handleUndoLast: function(e) {
            e.preventDefault();
            
            this.showModal(
                'Are you sure you want to undo the last price changes? This will restore all products to their previous prices.',
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
         * Handle clear logs - USES MODAL
         */
        handleClearLogs: function(e) {
            e.preventDefault();
            
            this.showModal(
                'Are you sure you want to clear all activity logs? This action cannot be undone.',
                'clear-logs'
            );
        },

        /**
         * Handle modal confirmation - HANDLES ALL MODAL ACTIONS
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
                default:
                    console.log('Unknown modal action:', action);
                    break;
            }
        },

        /**
         * Execute apply rules action
         */
        executeApplyRules: function() {
            var formData = this.getFormData();
            
            this.setButtonLoading('apply-rules', true);
            this.showLoading('Applying price changes...');
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_apply_rules',
                    nonce: pricetunex_ajax.nonce,
                    rule_type: formData.rule_type,
                    rule_value: formData.rule_value,
                    target_price_type: formData.target_price_type, // NEW
                    target_scope: formData.target_scope,
                    categories: formData.categories,
                    tags: formData.tags,
                    product_types: formData.product_types,
                    price_min: formData.price_min,
                    price_max: formData.price_max,
                    apply_rounding: formData.apply_rounding,
                    rounding_type: formData.rounding_type,
                    custom_ending: formData.custom_ending
                },
                success: function(response) {
                    if (response.success) {
                        PriceTuneXAdmin.showMessage('Successfully updated ' + response.data.products_updated + ' products.', 'success');
                        PriceTuneXAdmin.clearPreview();
                        PriceTuneXAdmin.loadLogs();
                        PriceTuneXAdmin.updateStatistics();
                        PriceTuneXAdmin.resetForm();
                    } else {
                        PriceTuneXAdmin.showMessage(response.data || 'Apply failed.', 'error');
                    }
                },
                error: function() {
                    PriceTuneXAdmin.showMessage('An error occurred while applying rules.', 'error');
                },
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('apply-rules', false);
                    PriceTuneXAdmin.hideLoading();
                }
            });
        },

        /**
         * Execute undo changes action
         */
        executeUndoChanges: function() {
            this.setButtonLoading('undo-last', true);
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_undo_changes',
                    nonce: pricetunex_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PriceTuneXAdmin.showMessage('Successfully restored ' + response.data.products_restored + ' products.', 'success');
                        PriceTuneXAdmin.loadLogs();
                        PriceTuneXAdmin.updateStatistics();
                    } else {
                        PriceTuneXAdmin.showMessage(response.data || 'No changes to undo.', 'error');
                    }
                },
                error: function() {
                    PriceTuneXAdmin.showMessage('An error occurred while undoing changes.', 'error');
                },
                complete: function() {
                    PriceTuneXAdmin.setButtonLoading('undo-last', false);
                }
            });
        },

        /**
         * Execute clear logs action
         */
        executeClearLogs: function() {
            this.setButtonLoading('clear-logs', true);
            
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
                }
            });
        },

        /**
         * Get form data - UPDATED WITH TARGET PRICE TYPE
         */
        getFormData: function() {
            var data = {};
            
            // Basic fields
            data.rule_type = $('#rule_type').val() || 'percentage';
            data.rule_value = parseFloat($('#rule_value').val()) || 0;
            data.target_price_type = $('#target_price_type').val() || 'smart'; // NEW
            data.target_scope = $('#target_scope').val() || 'all';
            
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
            data.rounding_type = $('#rounding_type').val() || '0.99';
            data.custom_ending = parseFloat($('#custom_ending').val()) || 0;
            
            // Debug log the form data
            console.log('PriceTuneX getFormData:', data);
            
            return data;
        },

        /**
         * Form validation - UPDATED WITH TARGET PRICE TYPE VALIDATION
         */
        validateForm: function(data) {
            // Check if rule value is provided
            if (!data.rule_value || data.rule_value === 0) {
                this.showMessage('Please enter a valid adjustment value.', 'error');
                $('#rule_value').focus();
                return false;
            }
            
            // Validate percentage range
            if (data.rule_type === 'percentage') {
                if (data.rule_value < -100 || data.rule_value > 1000) {
                    this.showMessage('Percentage must be between -100% and 1000%.', 'error');
                    $('#rule_value').focus();
                    return false;
                }
            }
            
            // Check scope-specific validations
            if (data.target_scope === 'categories' && (!data.categories || data.categories.length === 0)) {
                this.showMessage('Please select at least one category.', 'error');
                $('#categories').focus();
                return false;
            }
            
            if (data.target_scope === 'tags' && (!data.tags || data.tags.length === 0)) {
                this.showMessage('Please select at least one tag.', 'error');
                $('#tags').focus();
                return false;
            }
            
            if (data.target_scope === 'product_types' && (!data.product_types || data.product_types.length === 0)) {
                this.showMessage('Please select at least one product type.', 'error');
                return false;
            }
            
            if (data.target_scope === 'price_range' && data.price_min === 0 && data.price_max === 0) {
                this.showMessage('Please specify a price range.', 'error');
                $('#price_min').focus();
                return false;
            }
            
            // Validate price range logic
            if (data.target_scope === 'price_range' && data.price_min > 0 && data.price_max > 0) {
                if (data.price_min >= data.price_max) {
                    this.showMessage('Maximum price must be greater than minimum price.', 'error');
                    $('#price_max').focus();
                    return false;
                }
            }
            
            // Validate custom ending value
            if (data.apply_rounding && data.rounding_type === 'custom') {
                if (data.custom_ending < 0 || data.custom_ending >= 1) {
                    this.showMessage('Custom ending must be between 0.00 and 0.99.', 'error');
                    $('#custom_ending').focus();
                    return false;
                }
            }
            
            // NEW: Special validation for sale_only target price type
            if (data.target_price_type === 'sale_only') {
                // Show informational message about sale_only
                var infoMessage = 'Note: Only products with active sale prices will be updated when using "Sale Price Only" mode.';
                this.showMessage(infoMessage, 'info');
            }
            
            return true;
        },

        /**
         * Display preview results - UPDATED TO HANDLE BOTH PRICES STRUCTURE
         */
        displayPreview: function(data) {
            var html = '<div class="preview-summary">';
            html += '<h4>Products Affected: <span class="count">' + data.products_affected + '</span></h4>';

            if (data.preview_data && data.preview_data.products && data.preview_data.products.length > 0) {
                html += '<div class="preview-list">';

                // Show preview note if we're showing a limited sample
                if (data.products_affected > data.preview_data.products.length) {
                    html += '<p class="preview-note"><em>Showing first ' + data.preview_data.products.length + 
                           ' products (out of ' + data.products_affected + ' total that will be affected)</em></p>';
                }

                data.preview_data.products.forEach(function(product) {
                    html += PriceTuneXAdmin.renderPreviewItem(product);
                });

                html += '</div>';
            } else {
                html += '<p>No products found matching the criteria.</p>';
            }

            html += '</div>';
            $('#preview-results').html(html).addClass('fade-in');
        },

        /**
         * Render individual preview item - SIMPLIFIED
         */
        renderPreviewItem: function(product) {
            var html = '<div class="preview-item">';
            html += '<strong>' + product.name + '</strong><br>';

            // Show the primary change (what customers see)
            if (product.primary_change) {
                var change = product.primary_change;
                html += '<div class="primary-change">';
                html += '<span class="price-change">';
                html += change.formatted_old + ' → ' + change.formatted_new;
                html += ' <span class="change-amount ' + change.change_type + '">';
                html += (change.change_type === 'increase' ? '+' : '-') + change.formatted_change;
                html += '</span>';
                html += '</span>';
                html += '<div class="price-type-label">';
                html += '<small><em>' + change.label + '</em></small>';
                html += '</div>';
                html += '</div>';

                // Show detailed breakdown for both_prices mode
                if (change.type === 'both' && product.updates) {
                    html += '<div class="detailed-breakdown">';

                    if (product.updates.regular) {
                        html += '<div class="price-detail">';
                        html += '<span class="detail-label">Regular:</span> ';
                        html += product.regular_price.formatted_original + ' → ' + product.updates.regular.formatted_new;
                        html += '</div>';
                    }

                    if (product.updates.sale) {
                        html += '<div class="price-detail">';
                        html += '<span class="detail-label">Sale:</span> ';
                        html += product.sale_price.formatted_original + ' → ' + product.updates.sale.formatted_new;
                        html += '</div>';
                    }

                    html += '</div>';
                }
            }

            html += '</div>';
            return html;
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
            // Clear existing content and show single loading indicator
            $('#logs-container').html('<div class="logs-loading"><p>Loading logs...</p></div>');
            
            $.ajax({
                url: pricetunex_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pricetunex_get_logs',
                    nonce: pricetunex_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.logs) {
                        PriceTuneXAdmin.displayLogs(response.data.logs);
                    } else {
                        $('#logs-container').html('<p>No logs found.</p>');
                    }
                },
                error: function() {
                    $('#logs-container').html('<p>Error loading logs.</p>');
                }
            });
        },

        /**
         * Display logs
         */
        displayLogs: function(logs) {
            var html = '';
            
            if (logs.length === 0) {
                html = '<p>No activity logs found.</p>';
            } else {
                logs.forEach(function(log) {
                    html += '<div class="log-entry">';
                    html += '<div class="log-header">';
                    html += '<span class="log-date">' + (log.date || 'Unknown date') + '</span>';
                    html += '<span class="log-action ' + (log.action_type || '') + '">' + (log.action || 'Unknown action') + '</span>';
                    html += '</div>';
                    html += '<div class="log-details">';
                    html += '<p>' + (log.description || 'No description') + '</p>';
                    if (log.products_count) {
                        html += '<span class="products-count">Products affected: ' + log.products_count + '</span>';
                    }
                    html += '</div>';
                    html += '</div>';
                });
            }
            
            $('#logs-container').html(html);
        },

        /**
         * Update statistics display
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
                            if (stats.average_price) {
                                $('.stat-average-price').text('$' + stats.average_price.toFixed(2));
                            }
                        }
                    }
                },
                error: function() {
                    // Silently fail for stats - non-critical
                    console.log('Failed to load statistics');
                }
            });
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function(buttonId, loading) {
            var $button = $('#' + buttonId);
            if (loading) {
                $button.prop('disabled', true);
                if ($button.find('.spinner').length === 0) {
                    $button.append(' <span class="spinner is-active" style="float: none; margin-left: 5px;"></span>');
                }
            } else {
                $button.prop('disabled', false);
                $button.find('.spinner').remove();
            }
        },

        /**
         * Show loading overlay
         */
        showLoading: function(message) {
            $('#loading-message').text(message || 'Processing...');
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
         * Show message notification - UPDATED TO SUPPORT INFO TYPE
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            // Remove existing messages
            $('.pricetunex-message').remove();
            
            // Create new message
            var $message = $('<div class="pricetunex-message ' + type + '">' + message + '</div>');
            
            // Insert after the main heading
            $('.pricetunex-admin h1').after($message);
            
            // Auto-hide after 5 seconds (except for info messages which hide after 3 seconds)
            var hideDelay = type === 'info' ? 3000 : 5000;
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, hideDelay);
            
            // Scroll to top to show message
            $('html, body').animate({
                scrollTop: $('.pricetunex-admin').offset().top - 50
            }, 300);
        },

        /**
         * Reset form to initial state
         */
        resetForm: function() {
            $('#pricetunex-rules-form')[0].reset();
            this.initFormDependencies();
            this.clearPreview();
            
            // Clear any validation highlights
            $('.form-group').removeClass('error');
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on our admin page
        if ($('.pricetunex-admin').length) {
            console.log('PriceTuneX: Admin page detected, initializing...');
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
     * Handle window resize
     */
    $(window).resize(function() {
        // Add any responsive handling here if needed
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
     * Handle clicks outside modal to close
     */
    $(document).on('click', '.pricetunex-modal', function(e) {
        if (e.target === this) {
            PriceTuneXAdmin.hideModal();
        }
    });

    /**
     * Make the admin interface globally accessible for debugging
     */
    window.PriceTuneXAdmin = PriceTuneXAdmin;

    /**
     * Console log for debugging
     */
    if (typeof console !== 'undefined' && console.log) {
        console.log('PriceTuneX Admin JS loaded successfully - WITH TARGET PRICE TYPE SUPPORT');
    }




    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on our admin page
        if ($('.pricetunex-admin').length) {
            console.log('PriceTuneX: Admin page detected, initializing...');
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
     * Handle window resize
     */
    $(window).resize(function() {
        // Add any responsive handling here if needed
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
     * Handle clicks outside modal to close
     */
    $(document).on('click', '.pricetunex-modal', function(e) {
        if (e.target === this) {
            PriceTuneXAdmin.hideModal();
        }
    });

    /**
     * Make the admin interface globally accessible for debugging
     */
    window.PriceTuneXAdmin = PriceTuneXAdmin;

    /**
     * Console log for debugging
     */
    if (typeof console !== 'undefined' && console.log) {
        console.log('PriceTuneX Admin JS loaded successfully - WITH TARGET PRICE TYPE SUPPORT');
    }

})(jQuery);