<?php

/**
 * inject_module_dropdowns.php
 * Simple and independent script for PV module dropdowns
 */

// Include database configuration
require_once 'config/database.php';

// Buscar marcas do banco de dados
$brandsQuery = $pdo->query("SELECT id, brand_name FROM pv_module_brands ORDER BY brand_name");
$brands = $brandsQuery->fetchAll(PDO::FETCH_ASSOC);

// Converter para JSON para uso no JavaScript
$brandsJson = json_encode($brands);
?>

<!-- Simple script for PV module dropdowns -->
<script>
    console.log('=== INICIANDO PV MODULE DROPDOWN FIX ===');

    // Skip on reports page
    if (window.REPORTS_PAGE) {
        console.log('[PV Module Dropdowns] ⏭️ Skipping initialization on reports page');
    } else {

        // Dados pré-carregados do PHP
        const pvModuleBrands = <?php echo $brandsJson; ?>;

        // Function to load models
        function loadPVModels(brandId) {
            console.log('loadPVModels chamado com brandId:', brandId);

            const modelDropdown = document.getElementById('new_module_model');
            if (!modelDropdown) {
                console.error('Models dropdown not found');
                return;
            }

            // Clear dropdown
            modelDropdown.innerHTML = '<option value="">Loading...</option>';
            modelDropdown.disabled = true;

            if (!brandId) {
                modelDropdown.innerHTML = '<option value="">Select a model...</option>';
                modelDropdown.disabled = true;
                return;
            }

            // Use XMLHttpRequest for maximum compatibility
            const xhr = new XMLHttpRequest();
            const baseUrl = window.BASE_URL || '';
            xhr.open('GET', baseUrl + 'ajax/get_equipment_models.php?type=pv_module&brand_id=' + brandId, true);

            xhr.onload = function() {
                console.log('Response received:', xhr.responseText);

                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        console.log('Data parsed:', data);

                        // Reset dropdown
                        modelDropdown.innerHTML = '<option value="">Select a model...</option>';

                        if (Array.isArray(data) && data.length > 0) {
                            data.forEach(function(model) {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.model_name;
                                modelDropdown.appendChild(option);
                            });
                            console.log(data.length + ' models added');
                        } else {
                            console.warn('No models found');
                        }
                    } catch (e) {
                        console.error('Error processing JSON:', e);
                        modelDropdown.innerHTML = '<option value="">Error processing data</option>';
                    }
                } else {
                    console.error('HTTP Error:', xhr.status);
                    modelDropdown.innerHTML = '<option value="">Error loading models</option>';
                }

                modelDropdown.disabled = false;
            };

            xhr.onerror = function() {
                console.error('Network error');
                modelDropdown.innerHTML = '<option value="">Network error</option>';
                modelDropdown.disabled = false;
            };

            xhr.send();
        }

        // Function to initialize dropdowns
        function initPVModuleDropdowns() {
            console.log('Initializing PV module dropdowns...');

            const brandDropdown = document.getElementById('new_module_brand');
            const modelDropdown = document.getElementById('new_module_model');

            if (!brandDropdown) {
                console.error('Brands dropdown not found');
                return;
            }

            if (!modelDropdown) {
                console.error('Models dropdown not found');
                return;
            }

            console.log('Dropdowns found, loading brands...');

            // Clear brands dropdown
            brandDropdown.innerHTML = '<option value="">Select a brand...</option>';

            // Add pre-loaded brands
            if (Array.isArray(pvModuleBrands)) {
                pvModuleBrands.forEach(function(brand) {
                    const option = document.createElement('option');
                    option.value = brand.id;
                    option.textContent = brand.brand_name;
                    brandDropdown.appendChild(option);
                });
                console.log(pvModuleBrands.length + ' brands added');
            } else {
                console.error('Invalid data format for brands');
            }

            // Configure event listener
            brandDropdown.addEventListener('change', function() {
                console.log('Brand changed to:', this.value);
                loadPVModels(this.value);
            });

            console.log('Initialization completed');
        }

        // Execute immediately if elements already exist
        if (document.getElementById('new_module_brand') && document.getElementById('new_module_model')) {
            console.log('Elements found, executing immediately...');
            initPVModuleDropdowns();
        } else {
            // If they don't exist yet, wait for DOM
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM loaded, initializing...');
                initPVModuleDropdowns();
            });
        }

        console.log('Dropdown script loaded');

    } // End of !window.REPORTS_PAGE check
</script>