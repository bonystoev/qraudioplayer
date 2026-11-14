const CONFIG = {
  audioSrc: 'assets/audio.wav',
  coverSrc: 'assets/cover.svg',
  title: 'Titel des Werks',
  artist: 'Name der Künstlerin / des Künstlers',
  credits: [
    'Kurator:in: …',
    'Grafik & Sound: …',
    'Eine Ausstellung von …',
  ],
};

const ICON_PLAY = '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
const ICON_PAUSE = '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>';

const audio = new Audio(CONFIG.audioSrc);
audio.preload = 'metadata';

const coverEl = document.getElementById('cover');
const titleEl = document.getElementById('title');
const artistEl = document.getElementById('artist');
const playBtn = document.getElementById('play');
const stopBtn = document.getElementById('stop');
const seekEl = document.getElementById('seek');
const volumeEl = document.getElementById('volume');
const currentEl = document.getElementById('current');
const durationEl = document.getElementById('duration');
const waveCanvas = document.getElementById('waveform');
const waveCtx = waveCanvas.getContext('2d');
const creditsEl = document.getElementById('credits');

coverEl.src = CONFIG.coverSrc;
titleEl.textContent = CONFIG.title;
artistEl.textContent = CONFIG.artist;
document.title = CONFIG.title + ' – Audioguide';
if (Array.isArray(CONFIG.credits)) {
  for (const line of CONFIG.credits) {
    const lineEl = document.createElement('div');
    lineEl.textContent = line;
    creditsEl.appendChild(lineEl);
  }
}

let audioCtx = null;
let analyser = null;
let gainNode = null;
let timeData = null;

function ensureAudioGraph() {
  if (!audioCtx) {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    try {
      audioCtx = new Ctx();
      const source = audioCtx.createMediaElementSource(audio);
      gainNode = audioCtx.createGain();
      gainNode.gain.value = Number(volumeEl.value);
      audio.volume = 1;
      analyser = audioCtx.createAnalyser();
      analyser.fftSize = 2048;
      analyser.smoothingTimeConstant = 0.8;
      source.connect(gainNode);
      gainNode.connect(analyser);
      analyser.connect(audioCtx.destination);
      timeData = new Uint8Array(analyser.fftSize);
    } catch (e) {
      audioCtx = null;
      gainNode = null;
      return;
    }
  }
  if (audioCtx.state === 'suspended') audioCtx.resume();
}

let currentVolume = 1;

function setVolume(v) {
  currentVolume = v;
  if (audioCtx && gainNode) {
    gainNode.gain.setTargetAtTime(v, audioCtx.currentTime, 0.02);
  } else {
    audio.volume = v;
  }
}

function duckGain() {
  if (audioCtx && gainNode && !audio.paused && !audio.ended) {
    gainNode.gain.cancelScheduledValues(audioCtx.currentTime);
    gainNode.gain.setTargetAtTime(0, audioCtx.currentTime, 0.008);
  }
}

function restoreGain() {
  if (audioCtx && gainNode && !audio.paused && !audio.ended) {
    gainNode.gain.cancelScheduledValues(audioCtx.currentTime);
    gainNode.gain.setTargetAtTime(currentVolume, audioCtx.currentTime, 0.03);
  }
}

function setPlayingUI(playing) {
  playBtn.innerHTML = playing ? ICON_PAUSE : ICON_PLAY;
  playBtn.setAttribute('aria-label', playing ? 'Pause' : 'Abspielen');
  playBtn.classList.toggle('is-playing', playing);
}

function formatTime(sec) {
  if (!Number.isFinite(sec) || sec < 0) return '0:00';
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60);
  return m + ':' + String(s).padStart(2, '0');
}

function setFill(el, ratio) {
  el.style.setProperty('--fill', Math.round(ratio * 100) + '%');
}

playBtn.addEventListener('click', () => {
  ensureAudioGraph();
  if (audio.paused) {
    audio.play().catch(() => {});
  } else {
    audio.pause();
  }
});

stopBtn.addEventListener('click', () => {
  audio.pause();
  audio.currentTime = 0;
});

audio.addEventListener('play', () => setPlayingUI(true));
audio.addEventListener('pause', () => setPlayingUI(false));
audio.addEventListener('ended', () => { audio.currentTime = 0; });
audio.addEventListener('loadedmetadata', () => {
  durationEl.textContent = formatTime(audio.duration);
});
audio.addEventListener('seeking', duckGain);
audio.addEventListener('seeked', restoreGain);

let seekDragging = false;
seekEl.addEventListener('pointerdown', () => { seekDragging = true; });
window.addEventListener('pointerup', () => { seekDragging = false; });
seekEl.addEventListener('input', () => {
  if (Number.isFinite(audio.duration) && audio.duration > 0) {
    audio.currentTime = (Number(seekEl.value) / 1000) * audio.duration;
    currentEl.textContent = formatTime(audio.currentTime);
  }
});

audio.addEventListener('timeupdate', () => {
  currentEl.textContent = formatTime(audio.currentTime);
  if (!seekDragging && Number.isFinite(audio.duration) && audio.duration > 0) {
    const ratio = audio.currentTime / audio.duration;
    seekEl.value = String(Math.round(ratio * 1000));
    setFill(seekEl, ratio);
  }
});

let storedVolume = NaN;
try { storedVolume = Number(localStorage.getItem('audioplayer-volume')); } catch (e) { /* ignore */ }
if (Number.isFinite(storedVolume) && storedVolume >= 0 && storedVolume <= 1) {
  volumeEl.value = String(storedVolume);
}
setVolume(Number(volumeEl.value));
setFill(volumeEl, Number(volumeEl.value));
volumeEl.addEventListener('pointerdown', () => ensureAudioGraph());
volumeEl.addEventListener('input', () => {
  ensureAudioGraph();
  const v = Number(volumeEl.value);
  setVolume(v);
  setFill(volumeEl, v);
  try { localStorage.setItem('audioplayer-volume', String(v)); } catch (e) { /* ignore */ }
});

audio.addEventListener('error', () => {
  titleEl.textContent = 'Audiodatei nicht geladen';
  artistEl.textContent = 'Pfad in app.js prüfen: ' + CONFIG.audioSrc;
});

function resizeCanvas() {
  const dpr = window.devicePixelRatio || 1;
  const rect = waveCanvas.getBoundingClientRect();
  const w = Math.max(1, Math.round(rect.width * dpr));
  const h = Math.max(1, Math.round(rect.height * dpr));
  if (waveCanvas.width !== w || waveCanvas.height !== h) {
    waveCanvas.width = w;
    waveCanvas.height = h;
  }
}
window.addEventListener('resize', resizeCanvas);

function drawWaveform() {
  requestAnimationFrame(drawWaveform);
  resizeCanvas();
  const w = waveCanvas.width;
  const h = waveCanvas.height;
  const mid = h / 2;
  waveCtx.clearRect(0, 0, w, h);
  waveCtx.lineCap = 'round';
  waveCtx.lineWidth = Math.max(2, h * 0.045);
  if (analyser && !audio.paused && !audio.ended) {
    analyser.getByteTimeDomainData(timeData);
    waveCtx.strokeStyle = '#6ea8fe';
    waveCtx.shadowColor = 'rgba(110, 168, 254, 0.6)';
    waveCtx.shadowBlur = h * 0.12;
    waveCtx.beginPath();
    const step = w / (timeData.length - 1);
    for (let i = 0; i < timeData.length; i++) {
      const x = i * step;
      const y = mid + ((timeData[i] - 128) / 128) * (h * 0.46);
      if (i === 0) waveCtx.moveTo(x, y);
      else waveCtx.lineTo(x, y);
    }
    waveCtx.stroke();
    waveCtx.shadowBlur = 0;
  } else {
    waveCtx.strokeStyle = 'rgba(255, 255, 255, 0.16)';
    waveCtx.beginPath();
    waveCtx.moveTo(0, mid);
    waveCtx.lineTo(w, mid);
    waveCtx.stroke();
  }
}

setPlayingUI(false);
resizeCanvas();
drawWaveform();
