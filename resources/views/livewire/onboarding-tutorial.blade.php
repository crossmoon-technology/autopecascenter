<div
    x-data
    x-init="(() => {
        window.onboardingTourFinish = (orderId) => $wire.call('finish', orderId ?? null);
        window.dispatchEvent(new CustomEvent('onboarding-tour:ready', {
            detail: {
                needsTutorial: @js($visible),
                steps: @js($steps),
                finish: window.onboardingTourFinish,
            },
        }));
    })()"
></div>
