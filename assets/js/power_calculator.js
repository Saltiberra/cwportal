/**
 * Calculate and update the Installed Power (kWp) and Total Power (kWp) fields
 * - Installed Power: based on PV modules with "new" status only
 * - Total Power: based on PV modules with "new" OR "existing" status
 */
function updateInstalledPower() {
    // Skip on reports page
    if (window.REPORTS_PAGE) {
        return;
    }

    console.log('[Power Calculator] ===== STARTING updateInstalledPower() =====');
    console.log('[Power Calculator] modulesList length:', modulesList ? modulesList.length : 'undefined');
    console.log('[Power Calculator] modulesList:', modulesList);

    if (!modulesList || modulesList.length === 0) {
        console.log('[Power Calculator] No modules in list, setting powers to 0');
        // Set powers to 0 if no modules
        const installedPowerField = document.getElementById('installed_power');
        if (installedPowerField) {
            installedPowerField.value = '0.00';
            const event = new Event('change');
            installedPowerField.dispatchEvent(event);
        }
        const totalPowerField = document.getElementById('total_power');
        if (totalPowerField) {
            totalPowerField.value = '0.00';
            const event = new Event('change');
            totalPowerField.dispatchEvent(event);
        }
        return;
    }

    // DEBUG: Check each module's properties
    console.log('[Power Calculator] ===== MODULES DEBUG =====');
    modulesList.forEach((module, index) => {
        console.log(`[Power Calculator] Module ${index}:`, {
            brand_name: module.brand_name,
            model_name: module.model_name,
            quantity: module.quantity,
            power_rating: module.power_rating,
            status: module.status,
            power_options: module.power_options
        });
    });
    console.log('[Power Calculator] ===== END MODULES DEBUG =====');

    // Get all modules with "new" status for installed power
    const newModules = modulesList.filter(module => module.status === 'new');
    console.log('[Power Calculator] Filtered new modules:', newModules.length, 'from', modulesList.length);
    console.log('[Power Calculator] New modules details:', newModules);

    // Get all modules with "new" or "existing" status for total power
    const totalModules = modulesList.filter(module => module.status === 'new' || module.status === 'existing');
    console.log('[Power Calculator] Filtered total modules (new+existing):', totalModules.length, 'from', modulesList.length);
    console.log('[Power Calculator] Total modules details:', totalModules);

    // Calculate installed power (only new modules)
    let installedPowerWatts = 0;
    newModules.forEach(module => {
        if (module.power_rating && module.quantity) {
            const powerRating = parseFloat(module.power_rating);
            const quantity = parseInt(module.quantity);
            if (!isNaN(powerRating) && !isNaN(quantity) && powerRating > 0 && quantity > 0) {
                const moduleWatts = powerRating * quantity;
                console.log(`[Power Calculator] NEW module: ${module.model_name} = ${powerRating}W x ${quantity} = ${moduleWatts}W`);
                installedPowerWatts += moduleWatts;
            } else {
                console.log(`[Power Calculator] SKIPPED NEW module: ${module.model_name} - invalid values (power: ${module.power_rating}, qty: ${module.quantity})`);
            }
        } else {
            console.log(`[Power Calculator] SKIPPED NEW module: ${module.model_name} - missing power_rating or quantity`);
        }
    });
    console.log('[Power Calculator] Total installed power (new):', installedPowerWatts, 'W');

    // Calculate total power (new + existing modules)
    let totalPowerWatts = 0;
    totalModules.forEach(module => {
        if (module.power_rating && module.quantity) {
            const powerRating = parseFloat(module.power_rating);
            const quantity = parseInt(module.quantity);
            if (!isNaN(powerRating) && !isNaN(quantity) && powerRating > 0 && quantity > 0) {
                const moduleWatts = powerRating * quantity;
                console.log(`[Power Calculator] TOTAL module (${module.status}): ${module.model_name} = ${powerRating}W x ${quantity} = ${moduleWatts}W`);
                totalPowerWatts += moduleWatts;
            } else {
                console.log(`[Power Calculator] SKIPPED TOTAL module: ${module.model_name} - invalid values (power: ${module.power_rating}, qty: ${module.quantity})`);
            }
        } else {
            console.log(`[Power Calculator] SKIPPED TOTAL module: ${module.model_name} - missing power_rating or quantity`);
        }
    });
    console.log('[Power Calculator] Total power (new+existing):', totalPowerWatts, 'W');

    // Convert watts to kilowatts (kWp)
    const installedPowerKWp = installedPowerWatts / 1000;
    const totalPowerKWp = totalPowerWatts / 1000;

    console.log('[Power Calculator] Final calculations:');
    console.log('  - Installed Power (new only):', installedPowerKWp.toFixed(2), 'kWp');
    console.log('  - Total Power (new+existing):', totalPowerKWp.toFixed(2), 'kWp');

    // Update the installed power field
    const installedPowerField = document.getElementById('installed_power');
    if (installedPowerField) {
        console.log('[Power Calculator] Updating installed_power field to:', installedPowerKWp.toFixed(2));
        installedPowerField.value = installedPowerKWp.toFixed(2);

        // Trigger change event to activate autosave
        const event = new Event('change');
        installedPowerField.dispatchEvent(event);
    } else {
        console.error('[Power Calculator] installed_power field NOT FOUND!');
    }

    // Update the total power field
    const totalPowerField = document.getElementById('total_power');
    if (totalPowerField) {
        console.log('[Power Calculator] Updating total_power field to:', totalPowerKWp.toFixed(2));
        totalPowerField.value = totalPowerKWp.toFixed(2);

        // Trigger change event to activate autosave
        const event = new Event('change');
        totalPowerField.dispatchEvent(event);
    } else {
        console.error('[Power Calculator] total_power field NOT FOUND!');
    }

    console.log('[Power Calculator] ===== FINISHED updateInstalledPower() =====');
}
