// resources/js/filament-notification-sounds.js

// Assigned once: wire:navigate re-runs body scripts, and a second top-level
// `class` declaration throws "has already been declared".
window.FilamentNotificationSounds ??= class FilamentNotificationSounds {
    constructor() {
        this.enabled = this.getSoundPreference();
        this.volume = this.getVolumePreference();
        this.sounds = {};
        this.lastNotificationTime = 0;
        this.cooldownPeriod = 10000; // 1 second cooldown
        
        this.init();
    }

    init() {
        this.loadSounds();
        this.observeFilamentNotifications();
        this.addSoundControls();
    }

    // Load notification sounds
    loadSounds() {
        const soundFiles = {
            success: '/sounds/success.mp3',
            warning: '/sounds/warning.mp3',
            danger: '/sounds/error.mp3',
            info: '/sounds/info.mp3',
            default: '/sounds/notification.mp3'
        };

        // Create audio objects
        Object.keys(soundFiles).forEach(type => {
            const audio = new Audio();
            audio.preload = 'auto';
            audio.volume = this.volume;
            audio.src = soundFiles[type];
            
            // Handle loading errors silently
            audio.addEventListener('error', () => {
                console.warn(`Sound file not found: ${soundFiles[type]}`);
            });
            
            this.sounds[type] = audio;
        });

        // Create fallback beep sound
        this.createBeepSound();
    }

    // Create simple beep sound as fallback
    createBeepSound() {
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            
            this.beepSound = {
                play: (frequency = 800) => {
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
                    gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                    gainNode.gain.linearRampToValueAtTime(this.volume * 0.2, audioContext.currentTime + 0.01);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.2);
                }
            };
        } catch (error) {
            this.beepSound = { play: () => {} };
        }
    }

    // Watch for new Filament notifications
    observeFilamentNotifications() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        this.checkForNotifications(node);
                    }
                });
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // Check if added node is a Filament notification
    checkForNotifications(element) {
        // Filament notification selectors
        const notificationSelectors = [
            '.fi-no-notification',
            '[data-filament-notifications-container] > div',
            '.filament-notifications-container > div',
            '[role="alert"]'
        ];

        // Check if the element itself is a notification
        for (const selector of notificationSelectors) {
            if (element.matches && element.matches(selector)) {
                this.handleNotification(element);
                return;
            }
        }

        // Check for notifications within the element
        notificationSelectors.forEach(selector => {
            const notifications = element.querySelectorAll?.(selector) || [];
            notifications.forEach(notification => {
                this.handleNotification(notification);
            });
        });
    }

    // Handle detected notification
    handleNotification(notificationElement) {
        // Get the text content of the notification
        const notificationText = notificationElement.textContent || notificationElement.innerText || '';
        
        console.log('Notification detected with text:', notificationText); // Debug log
        
        // Check if the notification contains "New Documents Uploaded"
        if (notificationText.includes('New Documents Uploaded')) {
            this.playSound('success');
        } 
        else if (notificationText.includes('Document Rejected')) {
            this.playSound('danger');
        } 
        else if (notificationText.includes('New Comment') || notificationText.includes('Document Notes Updated')) {
            this.playSound('info');
        } else {
            return; // Silent for all other notifications
        }
    }

    // Play notification sound
    playSound(type = 'default') {
        if (!this.enabled) {
            console.log('Sound disabled, not playing'); // Debug log
            return;
        }

        // Implement cooldown
        const now = Date.now();
        if (now - this.lastNotificationTime < this.cooldownPeriod) {
            console.log('Sound blocked by cooldown'); // Debug log
            return;
        }
        this.lastNotificationTime = now;

        console.log(`Playing sound: ${type}`); // Debug log

        // Try to play the sound file
        const sound = this.sounds[type] || this.sounds.default;
        
        if (sound && sound.readyState >= 2) {
            sound.currentTime = 0;
            sound.volume = this.volume;
            
            const playPromise = sound.play();
            if (playPromise !== undefined) {
                playPromise
                    .then(() => {
                        console.log('Sound played successfully'); // Debug log
                    })
                    .catch((error) => {
                        console.log('Sound file failed, using beep:', error); // Debug log
                        this.playBeep(type);
                    });
            }
        } else {
            console.log('Sound file not ready, using beep'); // Debug log
            this.playBeep(type);
        }
    }

    // Play beep sound with different frequencies for different types
    playBeep(type) {
        const frequencies = {
            success: 600,
            warning: 800,
            danger: 1000,
            info: 400,
            default: 600
        };

        console.log(`Playing beep sound at ${frequencies[type] || frequencies.default}Hz`); // Debug log
        this.beepSound.play(frequencies[type] || frequencies.default);
    }

    // Add sound controls to Filament interface
    addSoundControls() {
        setTimeout(() => {
            const navbar = document.querySelector('.fi-topbar') || 
                          document.querySelector('.fi-main-topbar') ||
                          document.querySelector('[data-slot="topbar"]') ||
                          document.querySelector('nav');
            
        }, 2000); // Wait for Filament to fully load
    }



    // Attach event listeners to controls
    attachEventListeners(container) {
        const toggle = container.querySelector('#sound-toggle');
        const volumeSlider = container.querySelector('#volume-slider');

        if (toggle) {
            toggle.addEventListener('change', (e) => {
                this.enabled = e.target.checked;
                this.saveSoundPreference();
                this.updateControlIcon();
                console.log('Sound toggled:', this.enabled); // Debug log
            });
        }

        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                this.volume = parseFloat(e.target.value);
                this.updateVolume();
                this.saveVolumePreference();
                console.log('Volume changed:', this.volume); // Debug log
            });
        }
    }

    // Update control icon color
    updateControlIcon() {
        const icon = document.querySelector('#sound-controls svg');
        if (icon) {
            icon.className = icon.className.replace(/text-(green|gray)-\d+/, 
                this.enabled ? 'text-green-600' : 'text-gray-400');
        }
    }

    // Update volume for all sounds
    updateVolume() {
        Object.values(this.sounds).forEach(sound => {
            if (sound && sound.volume !== undefined) {
                sound.volume = this.volume;
            }
        });
    }

    // Test sound method - specific for document upload
    testDocumentUploadSound() {
        console.log('Testing document upload sound'); // Debug log
        this.playSound('success');
    }

    // Test sound method - specific for document rejected
    testDocumentRejectedSound() {
        console.log('Testing document rejected sound'); // Debug log
        this.playSound('danger');
    }

    // Test sound method - specific for info notifications
    testInfoSound() {
        console.log('Testing info sound'); // Debug log
        this.playSound('info');
    }

    // Test sound method (general)
    testSound(type) {
        console.log('Testing general sound:', type); // Debug log
        this.playSound(type);
    }

    // Simulate a document upload notification for testing
    simulateDocumentUploadNotification() {
        console.log('Simulating "New Documents Uploaded" notification'); // Debug log
        
        // Create a fake notification element
        const fakeNotification = document.createElement('div');
        fakeNotification.className = 'fi-no-notification';
        fakeNotification.textContent = 'New Documents Uploaded';
        
        // Handle it as if it was a real notification
        this.handleNotification(fakeNotification);
    }

    // Simulate a document rejected notification for testing
    simulateDocumentRejectedNotification() {
        console.log('Simulating "Document Rejected" notification'); // Debug log
        
        // Create a fake notification element
        const fakeNotification = document.createElement('div');
        fakeNotification.className = 'fi-no-notification';
        fakeNotification.textContent = 'Document Rejected';
        
        // Handle it as if it was a real notification
        this.handleNotification(fakeNotification);
    }

    // Preference management
    saveSoundPreference() {
        localStorage.setItem('filament-sound-enabled', this.enabled.toString());
    }

    saveVolumePreference() {
        localStorage.setItem('filament-sound-volume', this.volume.toString());
    }

    getSoundPreference() {
        const saved = localStorage.getItem('filament-sound-enabled');
        return saved !== null ? saved === 'true' : true;
    }

    getVolumePreference() {
        const saved = localStorage.getItem('filament-sound-volume');
        return saved !== null ? parseFloat(saved) : 0.7;
    }

    // Public methods
    enable() {
        this.enabled = true;
        this.saveSoundPreference();
        this.updateControlIcon();
        console.log('Sound system enabled'); // Debug log
    }

    disable() {
        this.enabled = false;
        this.saveSoundPreference();
        this.updateControlIcon();
        console.log('Sound system disabled'); // Debug log
    }

    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        this.updateVolume();
        this.saveVolumePreference();
        console.log('Volume set to:', this.volume); // Debug log
    }
};

// // Initialize when DOM is ready
// document.addEventListener('DOMContentLoaded', () => {
//     window.filamentSounds = new FilamentNotificationSounds();
//     console.log('FilamentNotificationSounds initialized'); // Debug log
// });

// // Also initialize if DOM is already loaded
// if (document.readyState === 'loading') {
//     document.addEventListener('DOMContentLoaded', () => {
//         window.filamentSounds = new FilamentNotificationSounds();
//         console.log('FilamentNotificationSounds initialized (DOM ready)'); // Debug log
//     });
// } else {
//     window.filamentSounds = new FilamentNotificationSounds();
//     console.log('FilamentNotificationSounds initialized (immediate)'); // Debug log
// }