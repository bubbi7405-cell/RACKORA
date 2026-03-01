class SoundManager {
    constructor() {
        this.ctx = null;
        this.masterGain = null;
        this.ambienceNode = null;
        this.isMuted = localStorage.getItem('server_tycoon_muted') === 'true';
        this.volume = parseFloat(localStorage.getItem('server_tycoon_volume') || '0.5');
    }

    init() {
        if (this.ctx) {
            this.resume();
            return;
        }

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.ctx = new AudioContext();
            this.masterGain = this.ctx.createGain();
            this.masterGain.gain.value = this.isMuted ? 0 : this.volume;
            this.masterGain.connect(this.ctx.destination);

            // Resume on creation if possible
            this.resume();
        } catch (e) {
            console.warn('Web Audio API not supported');
        }
    }

    /**
     * Resumes the AudioContext after a user gesture.
     * This is required by modern browsers.
     */
    async resume() {
        if (!this.ctx) return;
        if (this.ctx.state === 'suspended') {
            try {
                await this.ctx.resume();
                console.log('🔊 [SoundManager] AudioContext resumed successfully');
            } catch (e) {
                console.warn('🔊 [SoundManager] AudioContext resume failed:', e);
            }
        }
    }

    toggleMute() {
        return this.setMute(!this.isMuted);
    }

    setMute(muted) {
        this.isMuted = muted;
        if (this.masterGain) {
            this.masterGain.gain.value = this.isMuted ? 0 : this.volume;
        }
        localStorage.setItem('server_tycoon_muted', this.isMuted);
        return this.isMuted;
    }

    setVolume(val) {
        this.volume = Math.max(0, Math.min(1, val));
        if (this.masterGain && !this.isMuted) {
            this.masterGain.gain.value = this.volume;
        }
        localStorage.setItem('server_tycoon_volume', this.volume);
    }

    // --- Sound Synthesis ---

    playClick() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, this.ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(1200, this.ctx.currentTime + 0.05);

        gain.gain.setValueAtTime(0.3, this.ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.05);

        osc.start();
        osc.stop(this.ctx.currentTime + 0.05);
    }

    playSuccess() { // Ka-ching / Purchase
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'square';
        osc.frequency.setValueAtTime(440, t);
        osc.frequency.setValueAtTime(880, t + 0.1);

        gain.gain.setValueAtTime(0.1, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.4);

        osc.start();
        osc.stop(t + 0.4);
    }

    playError() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(150, t);
        osc.frequency.linearRampToValueAtTime(100, t + 0.3);

        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.3);

        osc.start();
        osc.stop(t + 0.3);
    }

    playNotification() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(500, t);
        osc.frequency.setValueAtTime(1000, t + 0.2);

        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.5);

        osc.start();
        osc.stop(t + 0.5);
    }

    playBreakingNews() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        // Classic "Ding-ding-ding" news alert
        osc.frequency.setValueAtTime(600, t);
        osc.frequency.setValueAtTime(0, t + 0.1);
        osc.frequency.setValueAtTime(600, t + 0.15);
        osc.frequency.setValueAtTime(0, t + 0.25);
        osc.frequency.setValueAtTime(800, t + 0.3);

        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.8);

        osc.start();
        osc.stop(t + 0.8);
    }

    playAlert() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(440, t);
        osc.frequency.linearRampToValueAtTime(880, t + 0.5);
        osc.frequency.linearRampToValueAtTime(440, t + 1.0);
        osc.frequency.linearRampToValueAtTime(880, t + 1.5);

        gain.gain.setValueAtTime(0.3, t);
        gain.gain.linearRampToValueAtTime(0.3, t + 1.9);
        gain.gain.linearRampToValueAtTime(0.01, t + 2.0);

        osc.start();
        osc.stop(t + 2.0);
    }

    playPowerOn() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(200, t);
        osc.frequency.exponentialRampToValueAtTime(800, t + 0.3);

        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.3);

        osc.start();
        osc.stop(t + 0.3);
    }

    playPowerOff() {
        if (!this.ctx) this.init();
        if (this.isMuted) return;

        const t = this.ctx.currentTime;
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();

        osc.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, t);
        osc.frequency.exponentialRampToValueAtTime(200, t + 0.4);

        gain.gain.setValueAtTime(0.2, t);
        gain.gain.exponentialRampToValueAtTime(0.01, t + 0.4);

        osc.start();
        osc.stop(t + 0.4);
    }

    startAmbience() {
        if (!this.ctx) this.init();
        if (this.ambienceNode) return; // Already playing

        // Create low hum
        const osc = this.ctx.createOscillator();
        const gain = this.ctx.createGain();
        const filter = this.ctx.createBiquadFilter();

        osc.connect(filter);
        filter.connect(gain);
        gain.connect(this.masterGain);

        osc.type = 'sawtooth';
        osc.frequency.value = 50; // Low hum
        filter.type = 'lowpass';
        filter.frequency.value = 120; // Muffle it

        gain.gain.value = 0.05; // Very quiet

        osc.start();
        this.ambienceNode = { osc, gain };
    }

    stopAmbience() {
        if (this.ambienceNode) {
            this.ambienceNode.osc.stop();
            this.ambienceNode = null;
        }
    }
}

export default new SoundManager();
