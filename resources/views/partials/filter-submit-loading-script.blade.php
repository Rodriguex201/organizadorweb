<script>
    window.initFilterSubmitLoading = function initFilterSubmitLoading(config) {
        const form = document.getElementById(config.formId);
        const submitButton = document.getElementById(config.submitButtonId);
        const submitLabel = document.getElementById(config.submitLabelId);
        const submitSpinner = document.getElementById(config.submitSpinnerId);
        const resultsArea = config.resultsAreaId ? document.getElementById(config.resultsAreaId) : null;
        const resultsOverlay = config.resultsOverlayId ? document.getElementById(config.resultsOverlayId) : null;
        const disableTargets = Array.isArray(config.disableTargetIds)
            ? config.disableTargetIds.map((id) => document.getElementById(id)).filter(Boolean)
            : [];

        if (!form || !submitButton || !submitLabel || !submitSpinner) {
            return;
        }

        let isLoading = false;
        let overlayTimer = null;

        const clearOverlay = () => {
            if (overlayTimer) {
                window.clearTimeout(overlayTimer);
                overlayTimer = null;
            }

            if (resultsOverlay) {
                resultsOverlay.classList.add('hidden');
                resultsOverlay.classList.remove('flex');
            }
        };

        const setLoadingState = (loading) => {
            isLoading = loading;

            Array.from(form.querySelectorAll('button[type="submit"]')).forEach((button) => {
                button.disabled = loading;
            });

            disableTargets.forEach((element) => {
                if ('disabled' in element) {
                    element.disabled = loading;
                }

                element.classList.toggle('pointer-events-none', loading);
                element.classList.toggle('opacity-70', loading);
            });

            submitSpinner.classList.toggle('hidden', !loading);
            submitLabel.textContent = loading ? (config.loadingText || 'Cargando...') : (config.idleText || 'Filtrar');

            if (!loading) {
                clearOverlay();
            }
        };

        setLoadingState(false);

        form.addEventListener('submit', (event) => {
            if (isLoading) {
                event.preventDefault();
                return;
            }

            setLoadingState(true);

            if (resultsArea && resultsOverlay && config.overlayMessage) {
                overlayTimer = window.setTimeout(() => {
                    if (!isLoading) {
                        return;
                    }

                    const areaHeight = Math.max(resultsArea.offsetHeight, config.minOverlayHeight || 140);
                    resultsArea.style.minHeight = `${areaHeight}px`;
                    resultsOverlay.classList.remove('hidden');
                    resultsOverlay.classList.add('flex');
                }, config.overlayDelayMs || 500);
            }
        });

        window.addEventListener('pageshow', () => {
            setLoadingState(false);
        });
    };
</script>
