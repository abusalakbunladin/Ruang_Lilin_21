function iconSvg(name){
return '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'+window.ICONS[name]+'</svg>';
}
const CARD_POOL = [
{id:'draw3', name:'Tarik Kartu 3', desc:'Tarik kartu bernilai 3 dari dek jika masih ada.', icon:'draw', weight:3, num:3},
{id:'draw5', name:'Tarik Kartu 5', desc:'Tarik kartu bernilai 5 dari dek jika masih ada.', icon:'draw', weight:3, num:5},
{id:'draw7', name:'Tarik Kartu 7', desc:'Tarik kartu bernilai 7 dari dek jika masih ada.', icon:'draw', weight:2, num:7},
{id:'draw9', name:'Tarik Kartu 9', desc:'Tarik kartu bernilai 9 dari dek jika masih ada.', icon:'draw', weight:2, num:9},
{id:'perfect', name:'Tarikan Sempurna', desc:'Tarik kartu terbaik yang tersisa di dek untuk dirimu.', icon:'perfect', weight:1},
{id:'destroy', name:'Hancurkan', desc:'Kembalikan kartu terbuka terakhir lawan ke dalam dek.', icon:'destroy', weight:2},
{id:'return', name:'Tarik Ulang', desc:'Kembalikan kartu terbukamu sendiri yang terakhir ke dek.', icon:'undo', weight:2},
{id:'exchange', name:'Pertukaran', desc:'Tukar kartu terakhirmu dengan kartu terakhir lawan.', icon:'exchange', weight:1},
{id:'goFor17', name:'Incar 17', desc:'Ubah target ronde ini menjadi 17.', icon:'target', weight:2, badge:17},
{id:'goFor24', name:'Incar 24', desc:'Ubah target ronde ini menjadi 24.', icon:'target', weight:2, badge:24},
{id:'goFor27', name:'Incar 27', desc:'Ubah target ronde ini menjadi 27.', icon:'target', weight:1, badge:27},
{id:'oneUp', name:'Naikkan Taruhan', desc:'Jika lawan kalah ronde ini, ia kehilangan 1 nyala tambahan.', icon:'up', weight:2},
{id:'desperation', name:'Keringanan', desc:'Jika kau kalah ronde ini, kau tidak kehilangan nyala.', icon:'down', weight:1},
{id:'shield', name:'Perisai', desc:'Menangkal satu efek berbahaya berikutnya dari lawan.', icon:'shield', weight:2},
{id:'loveEnemy', name:'Kasih Untuk Musuh', desc:'Lawan menerima kartu terbaik untuknya sendiri &mdash; bisa berkah, bisa bencana.', icon:'heart', weight:1},
{id:'trumpSwitch', name:'Tukar Takdir', desc:'Buang hingga 2 kartu spesial acakmu, lalu ambil 3 kartu spesial baru.', icon:'cycle', weight:1},
{id:'remove', name:'Sita', desc:'Buang satu kartu spesial acak dari tangan lawan.', icon:'remove', weight:2}
];
const HARMFUL = new Set(['destroy','remove','oneUp','deputyCurse','demonGrip','eternalSilence']);
const SELF_DRAW_IDS = new Set(['draw3','draw5','draw7','draw9','perfect']);
const PERSISTENT = new Set(['goFor17','goFor24','goFor27','oneUp','desperation','shield','eternalSilence','demonGrip']);
const HAND_CAP = 10;
const TABLE_LIMIT = 5;
const MAX_LIFE_PLAYER = 6;
const ENEMY_EXCLUSIVE = {
3: [ 
{id:'deputyCurse', name:'Kutukan Wakil', desc:'Buang satu kartu spesial acak milik lawan, lalu paksa lawan menarik kartu bernilai tertinggi yang tersisa di dek.', icon:'curseMark', weight:2},
{id:'demonGrip', name:'Cengkeraman Iblis', desc:'Buang separuh kartu spesial milik sendiri, lalu tarik kartu terbaik untuk diri sendiri. Taruhan lawan naik 6 selama kartu ini di meja.', icon:'clawGrip', weight:2}
],
4: [ 
{id:'deputyCurse', name:'Kutukan Wakil', desc:'Buang satu kartu spesial acak milik lawan, lalu paksa lawan menarik kartu bernilai tertinggi yang tersisa di dek.', icon:'curseMark', weight:1},
{id:'demonGrip', name:'Cengkeraman Iblis', desc:'Buang separuh kartu spesial milik sendiri, lalu tarik kartu terbaik untuk diri sendiri. Taruhan lawan naik 6 selama kartu ini di meja.', icon:'clawGrip', weight:1},
{id:'kingsWill', name:'Kehendak Raja', desc:'Batalkan ronde ini sepenuhnya dan mulai ronde baru, apa pun kondisinya sekarang.', icon:'royalEye', weight:2},
{id:'eternalSilence', name:'Keheningan Abadi', desc:'Selama kartu ini di meja, lawan tidak bisa menarik kartu sama sekali, bahkan lewat efek kartu spesial.', icon:'sealedEye', weight:2}
]
};
const ENEMY_ONLY_IDS = new Set(['deputyCurse','demonGrip','kingsWill','eternalSilence']);
const DIFFICULTIES = {
mudah: { label:'Mode Bayi', baseSkill:0.30, enrage:0.00 },
normal: { label:'Normal', baseSkill:0.46, enrage:0.05 },
sulit: { label:'Sulit', baseSkill:0.58, enrage:0.09 }
};
const STAGES = [
{
key:'tawanan', name:'Sang Tawanan', maxLife:5, stageBonus:0.00,
intro:'Ruangan pertama nyaris kosong, hanya diterangi satu lilin usang. Di seberang meja duduk seorang tawanan lain, tangannya gemetar saat mengocok kartu. "Maaf," bisiknya, nyaris tak terdengar. "Tapi cuma satu dari kita yang boleh lanjut ke ruangan berikutnya."',
clearWin:'Sang Tawanan terdiam. Lilinnya padam pelan, dan matanya seperti lega. Pintu di belakangnya berderit terbuka menuju ruangan yang lebih dalam.',
loseText:'Bahkan tawanan yang gemetar itu berhasil memadamkan lilinmu lebih dulu. Ia menatapmu sebentar, lalu menunduk seolah minta maaf, sebelum semuanya gelap.'
},
{
key:'penjaga', name:'Sang Penjaga', maxLife:6, stageBonus:0.05,
intro:'Sang Penjaga menata dek dengan tangan yang jauh lebih tenang. "Kau berhasil melewati tawanan itu," katanya datar. "Mari kita lihat apa kau seberuntung itu lagi."',
clearWin:'Sang Penjaga akhirnya diam. Lilinnya meleleh habis di atas meja. Di balik topengnya, sesuatu yang lain kini menunggumu di ruangan berikutnya.',
loseText:'Sang Penjaga menyaksikan lilinmu padam tanpa berkedip sekalipun.'
},
{
key:'sekretaris', name:'Sang Sekretaris', maxLife:6, stageBonus:0.10,
intro:'Seorang wanita berjas usang duduk rapi di balik meja, menyusun kartu seperti berkas administrasi. "Namamu sudah tercatat," katanya sambil tersenyum tipis. "Tinggal menunggu tanda tangan terakhir."',
clearWin:'Sang Sekretaris menutup map hitamnya perlahan. "Berkasmu naik ke atas," bisiknya, sebelum lilinnya padam sepenuhnya.',
loseText:'Sang Sekretaris mencatat sesuatu di map hitamnya sebelum lilinmu benar-benar padam.'
},
{
key:'wakilraja', name:'Wakil Raja Iblis', maxLife:7, stageBonus:0.38,
intro:'Ruangan ini terasa lebih dingin. Sesosok bertanduk kecil duduk santai, mengocok kartu dengan jari-jari yang terlalu panjang. "Sudah sejauh ini? Menarik. Biar aku yang mengajarimu arti kalah yang sesungguhnya."',
clearWin:'Sang Wakil tertawa pahit saat lilinnya padam. "Raja akan senang bertemu denganmu," desisnya sebelum menghilang ke kegelapan.',
loseText:'Tawa Sang Wakil masih terngiang lama setelah lilinmu padam.'
},
{
key:'rajaiblis', name:'Raja Iblis', maxLife:8, stageBonus:0.52,
intro:'Di ruangan terakhir, sesosok agung duduk di atas takhta kecil yang tersusun dari lilin-lilin leleh. "Begitu banyak yang mencoba, begitu sedikit yang sampai sejauh ini," katanya. "Mari kita akhiri ini, tawanan kecil."',
clearWin:'Raja Iblis mendesis panjang saat lilin terakhirnya padam total. Ruangan itu runtuh dalam gelap, dan kau berlari menembus pintu yang akhirnya benar-benar terbuka. Kau bebas. Sungguh-sungguh bebas.',
loseText:'Lilinmu padam di hadapan Raja Iblis sendiri. Di kursi ini, tawanan berikutnya akan duduk esok malam, tanpa pernah tahu namamu.'
}
];
let difficulty = 'normal';
let stageIndex = 0;
let carriedPlayerLife = MAX_LIFE_PLAYER;
let totalRoundsPlayed = 0;
let godModeActive = false;
let godModeImmune = false;
function oppName(){ return STAGES[stageIndex].name; }
function computeStartingPlayerLife(){
if(stageIndex === 0) return MAX_LIFE_PLAYER;
if(difficulty === 'mudah') return MAX_LIFE_PLAYER;
if(difficulty === 'normal') return Math.min(MAX_LIFE_PLAYER, carriedPlayerLife + 1);
return Math.max(0, carriedPlayerLife); 
}
let uidCounter = 1;
function uid(){ return uidCounter++; }
function drawSpecialInstance(pool, existingHand){
pool = pool || CARD_POOL;
const existingIds = new Set((existingHand||[]).map(c=>c.id));
let choices = pool.filter(c=>!existingIds.has(c.id));
if(choices.length===0) choices = pool;
const total = choices.reduce((s,c)=>s+c.weight,0);
let r = Math.random()*total;
for(const c of choices){
r -= c.weight;
if(r<=0) return Object.assign({}, c, {uid: uid()});
}
return Object.assign({}, choices[0], {uid: uid()});
}
let audioCtx = null;
let audioMuted = false;
function ensureAudio(){
if(!audioCtx){
try{ audioCtx = new (window.AudioContext||window.webkitAudioContext)(); }catch(e){ audioCtx = null; }
}
if(audioCtx && audioCtx.state==='suspended') audioCtx.resume();
}
function tone(freq, dur, type, gainPeak, delay){
if(!audioCtx || audioMuted) return;
type = type||'sine'; gainPeak = gainPeak==null?0.15:gainPeak; delay = delay||0;
const t0 = audioCtx.currentTime + delay;
const osc = audioCtx.createOscillator();
const gain = audioCtx.createGain();
osc.type = type; osc.frequency.setValueAtTime(freq, t0);
gain.gain.setValueAtTime(0.0001, t0);
gain.gain.linearRampToValueAtTime(gainPeak, t0+0.025);
gain.gain.exponentialRampToValueAtTime(0.0001, t0+dur);
osc.connect(gain); gain.connect(audioCtx.destination);
osc.start(t0); osc.stop(t0+dur+0.05);
}
function noiseBurst(dur, gainPeak, delay){
if(!audioCtx || audioMuted) return;
dur = dur||0.4; gainPeak = gainPeak==null?0.2:gainPeak; delay = delay||0;
const t0 = audioCtx.currentTime + delay;
const bufferSize = Math.max(1, Math.floor(audioCtx.sampleRate*dur));
const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
const data = buffer.getChannelData(0);
for(let i=0;i<bufferSize;i++){ data[i] = (Math.random()*2-1) * (1 - i/bufferSize); }
const src = audioCtx.createBufferSource();
src.buffer = buffer;
const filter = audioCtx.createBiquadFilter();
filter.type = 'lowpass'; filter.frequency.value = 900;
const gain = audioCtx.createGain();
gain.gain.setValueAtTime(gainPeak, t0);
gain.gain.exponentialRampToValueAtTime(0.001, t0+dur);
src.connect(filter); filter.connect(gain); gain.connect(audioCtx.destination);
src.start(t0);
}
function sfxWinRound(){ tone(440,0.18,'sine',0.14,0); tone(660,0.26,'sine',0.14,0.12); }
function sfxLoseRound(){ tone(130,0.35,'sawtooth',0.13,0); noiseBurst(0.3,0.10,0.05); }
function sfxDraw(){ tone(320,0.45,'sine',0.08,0); }
function sfxDoubleBust(){ tone(95,0.35,'square',0.11,0); tone(90,0.35,'square',0.11,0.12); }
function sfxStageClear(){ tone(523,0.2,'sine',0.14,0); tone(659,0.2,'sine',0.14,0.16); tone(784,0.4,'sine',0.14,0.32); }
function sfxGameOverLose(){ tone(160,0.8,'sawtooth',0.14,0); tone(105,1.0,'sawtooth',0.11,0.3); noiseBurst(0.6,0.1,0.1); }
function sfxTrueWin(){ tone(523,0.25,'sine',0.15,0); tone(659,0.25,'sine',0.15,0.2); tone(784,0.25,'sine',0.15,0.4); tone(1046,0.55,'sine',0.17,0.62); }
function sfxSpecialCard(){ tone(520,0.09,'triangle',0.08,0); tone(760,0.11,'triangle',0.06,0.05); }
function sfxExclusiveCard(){ tone(140,0.3,'sawtooth',0.13,0); tone(90,0.4,'sawtooth',0.14,0.12); tone(700,0.15,'square',0.07,0.28); }
function announceCard(card, actorKey){
const el = document.getElementById('card-announce');
const iconEl = document.getElementById('announce-icon');
const tagEl = document.getElementById('announce-tag');
const nameEl = document.getElementById('announce-name');
const isExclusive = ENEMY_ONLY_IDS.has(card.id);
iconEl.innerHTML = iconSvg(card.icon);
tagEl.textContent = isExclusive ? 'KARTU RAHASIA '+oppName().toUpperCase() : oppName().toUpperCase()+' MEMAINKAN';
nameEl.textContent = card.name + (card.badge ? ' '+card.badge : '');
el.classList.remove('hidden','show','exclusive');
void el.offsetWidth;
if(isExclusive){ el.classList.add('exclusive'); sfxExclusiveCard(); }
el.classList.add('show');
setTimeout(()=>{ el.classList.add('hidden'); el.classList.remove('show'); }, 1500);
}
function sfxHit(){ tone(700,0.05,'square',0.05,0); }
const NONBOSS_VOLUME = 0.16; 
const BOSS_VOLUME = 0.42; 
const DUCK_FACTOR = 0.35; 
let currentTrackIdx = null;
let ducked = false;
function isBossStage(idx){ return idx >= STAGES.length-1; }
const LEVEL_MUSIC_URLS=[];
function b64PartsForStage(idx){
if(idx>=0 && idx<4) return window['LEVEL_'+(idx+1)+'_MUSIC_B64_PARTS'];
return window.MUSIC_BOSS_MUSIC_B64_PARTS;
}
function musicBlobUrlForStage(idx){
if(LEVEL_MUSIC_URLS[idx]) return LEVEL_MUSIC_URLS[idx];
const b64parts = b64PartsForStage(idx);
if(!b64parts) return '';
const bytes=[];
for(let p=0;p<b64parts.length;p++){
const bin=atob(b64parts[p]);
const part=new Uint8Array(bin.length);
for(let i=0;i<bin.length;i++) part[i]=bin.charCodeAt(i);
bytes.push(part);
}
const url=URL.createObjectURL(new Blob(bytes,{type:'audio/mpeg'}));
LEVEL_MUSIC_URLS[idx]=url;
return url;
}
function targetVolume(idx){
if(audioMuted) return 0;
const base = isBossStage(idx) ? BOSS_VOLUME : NONBOSS_VOLUME;
return ducked ? base*DUCK_FACTOR : base;
}
function fadeTo(el, vol, ms){
const start = el.volume;
const t0 = performance.now();
function step(now){
const p = Math.min(1, (now-t0)/ms);
const v = start + (vol-start)*p;
el.volume = Math.max(0, Math.min(1, v));
if(p<1) requestAnimationFrame(step);
}
requestAnimationFrame(step);
}
function activeMusicEl(idx){ return isBossStage(idx) ? document.getElementById('bgm-boss') : document.getElementById('bgm-nonboss'); }
function updateStageMusic(){
const wanted = stageIndex;
const wantedEl = activeMusicEl(wanted);
const otherEl = isBossStage(wanted) ? document.getElementById('bgm-nonboss') : document.getElementById('bgm-boss');
const url = musicBlobUrlForStage(wanted);
currentTrackIdx = wanted;
try{ otherEl.pause(); otherEl.currentTime=0; }catch(e){}
if(wantedEl.src !== url){
wantedEl.src = url;
}
wantedEl.play().catch(err=>console.warn('Musik latar gagal diputar otomatis:', err && err.message));
fadeTo(wantedEl, targetVolume(wanted), 1200);
}
function retryMusicIfStalled(){
if(currentTrackIdx===null || audioMuted) return;
const el = activeMusicEl(currentTrackIdx);
if(el && el.paused && el.src){ el.play().catch(()=>{}); }
}
function duckMusic(on){
ducked = on;
const el = activeMusicEl(currentTrackIdx);
if(el) fadeTo(el, targetVolume(currentTrackIdx), 350);
}
function refreshMusicVolume(){
const el = activeMusicEl(currentTrackIdx);
if(el) fadeTo(el, targetVolume(currentTrackIdx), 300);
}
function flashFx(kind){
const el = document.getElementById('fx-flash');
el.className = '';
void el.offsetWidth;
el.classList.add(kind);
}
function pulseCandleHit(svgId){
const svg = document.getElementById(svgId);
if(!svg) return;
svg.classList.remove('hit');
void svg.offsetWidth;
svg.classList.add('hit');
setTimeout(()=>svg.classList.remove('hit'), 700);
}
let S = null;
let sid = 0; 
function freshDeck(){
const d = [1,2,3,4,5,6,7,8,9,10,11];
for(let i=d.length-1;i>0;i--){
const j = Math.floor(Math.random()*(i+1));
[d[i],d[j]]=[d[j],d[i]];
}
return d;
}
function popRandom(arr){
if(arr.length===0) return null;
const i = Math.floor(Math.random()*arr.length);
return arr.splice(i,1)[0];
}
function removeValue(arr, val){
const i = arr.indexOf(val);
if(i>=0){ arr.splice(i,1); return true; }
return false;
}
function initStageMatch(){
sid += 1;
const stage = STAGES[stageIndex];
S = {
round: 0,
lives: {player: computeStartingPlayerLife(), opponent: stage.maxLife},
player: {hidden:null, visible:[], specials:[]},
opponent: {hidden:null, visible:[], specials:[], stageLossCount:0, desperateAnnounced:false},
deck: [],
target: 21,
turn: 'player',
history: [],
awaitingContinue: false,
gameOver: false
};
clearLog();
updateStageHeader();
log('sys', 'Kau kini berhadapan dengan <b>'+stage.name+'</b>.');
startRound(true);
}
function updateStageHeader(){
document.getElementById('tag-stage-num').textContent = (stageIndex+1)+'/'+STAGES.length;
document.getElementById('tag-stage-name').textContent = STAGES[stageIndex].name;
document.getElementById('opp-name-tag').textContent = STAGES[stageIndex].name;
updateStageMusic();
renderStageDecor();
}
function renderStageDecor(){
const el = document.getElementById('stage-decorations');
el.className = 'stage-decorations stage-'+stageIndex;
let html = '';
if(stageIndex===0){
html = '<div class="deco-icon deco-skull">'+iconSvg('skull')+'</div>'+
'<div class="deco-icon deco-shackle">'+iconSvg('shackle')+'</div>'+
'<div class="deco-icon deco-skull2">'+iconSvg('skull')+'</div>';
} else if(stageIndex===1){
html = '<div class="deco-icon deco-shackle">'+iconSvg('shackle')+'</div>'+
'<div class="deco-icon deco-shield">'+iconSvg('guardShield')+'</div>'+
'<div class="deco-icon deco-shackle2">'+iconSvg('shackle')+'</div>';
} else if(stageIndex===2){
html = '<div class="deco-icon deco-papers">'+iconSvg('papers')+'</div>'+
'<div class="deco-icon deco-clipboard">'+iconSvg('clipboard')+'</div>'+
'<div class="deco-icon deco-papers2">'+iconSvg('papers')+'</div>';
} else if(stageIndex===3){
html = '<div class="deco-icon deco-stairs">'+iconSvg('stairs')+'</div>'+
'<div class="deco-icon deco-soul">'+iconSvg('soul')+'</div>'+
'<div class="deco-icon deco-soul2">'+iconSvg('soul')+'</div>';
} else {
html = '<div class="deco-icon deco-throne">'+iconSvg('throne')+'</div>'+
'<div class="deco-icon deco-soul">'+iconSvg('soul')+'</div>'+
'<div class="deco-icon deco-soul2">'+iconSvg('soul')+'</div>';
}
el.innerHTML = html;
const impRow = document.getElementById('imp-row');
if(stageIndex === STAGES.length-1){
impRow.classList.add('active');
impRow.innerHTML = '<div class="imp-face">'+iconSvg('impFace')+'</div>'.repeat(3);
} else {
impRow.classList.remove('active','cheer','boo');
impRow.innerHTML = '';
}
}
function startRound(isFirst){
S.round += 1;
totalRoundsPlayed += 1;
dealRound();
}
function dealRound(){
sid += 1;
S.deck = freshDeck();
S.player.hidden = popRandom(S.deck);
S.opponent.hidden = popRandom(S.deck);
S.player.visible = [];
S.opponent.visible = [];
S.target = 21;
S.history = [];
S.player.shieldActive = false;
S.opponent.shieldActive = false;
S.player.betMod = 0;
S.opponent.betMod = 0;
S.player.noLossIfLose = false;
S.opponent.noLossIfLose = false;
S.player.silenced = false;
S.opponent.silenced = false;
S.player.playedThisRound = [];
S.opponent.playedThisRound = [];
S.player.tableSlotsUsed = 0;
S.opponent.tableSlotsUsed = 0;
const n = trumpsPerRound();
for(let i=0;i<n;i++){
if(S.player.specials.length < HAND_CAP) S.player.specials.push(drawSpecialInstance(CARD_POOL, S.player.specials));
if(S.opponent.specials.length < HAND_CAP) S.opponent.specials.push(drawSpecialInstance(opponentCardPool(), S.opponent.specials));
}
S.turn = (S.round % 2 === 1) ? 'player' : 'opponent';
log('sys', 'Ronde '+S.round+' dimulai. Lilin di meja berkedip pelan.');
const pStart = popRandom(S.deck);
const oStart = popRandom(S.deck);
if(pStart!=null) S.player.visible.push(pStart);
if(oStart!=null) S.opponent.visible.push(oStart);
if(pStart!=null && oStart!=null){
log('sys', 'Kartu awal dibagikan: kau dapat <b>'+pStart+'</b>, '+oppName()+' dapat <b>'+oStart+'</b>.');
}
render();
if(isDesperatePhase() && !S.opponent.desperateAnnounced){
S.opponent.desperateAnnounced = true;
setTimeout(()=>{ announceDesperatePhase(); }, 500);
}
if(S.turn === 'opponent'){
setTurnLocked(true);
const _sid = sid;
setTimeout(()=>{ if(_sid===sid) aiTurn(); }, 900);
} else {
setTurnLocked(false);
}
}
function log(cls, msg){
const panel = document.getElementById('log-panel');
const div = document.createElement('div');
div.className = 'entry '+cls;
div.innerHTML = msg;
panel.appendChild(div);
panel.scrollTop = panel.scrollHeight;
}
function clearLog(){
document.getElementById('log-panel').innerHTML = '';
}
function pick(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
function trueTotal(side){
const s = S[side];
return s.hidden + s.visible.reduce((a,b)=>a+b,0);
}
function visibleTotal(side){
return S[side].visible.reduce((a,b)=>a+b,0);
}
function bestValueFor(currentTotal, deck, target){
if(deck.length===0) return null;
const candidates = deck.filter(v => currentTotal+v <= target);
if(candidates.length) return Math.max(...candidates);
return Math.min(...deck);
}
function applySpecial(actorKey, card){
const actor = S[actorKey];
const oppKey = actorKey==='player' ? 'opponent' : 'player';
const opp = S[oppKey];
const actorLabel = actorKey==='player' ? 'Kau' : oppName();
const oppLabel = oppKey==='player' ? 'kau' : oppName();
log(actorKey==='player'?'you':'opp', actorLabel+' memainkan kartu spesial: <b>'+card.name+'</b>.');
sfxSpecialCard();
if(actorKey==='opponent'){
announceCard(card, actorKey);
}
if(S.history.length>0){
S.history = [];
log('sys', 'Keadaan berubah &mdash; kedua pihak perlu bertahan lagi agar ronde berakhir.');
}
let blocked = false;
if(HARMFUL.has(card.id) && opp.shieldActive){
opp.shieldActive = false;
blocked = true;
log('sys', (oppKey==='player'?'Kau':oppName())+' menangkal efek itu dengan <b>Perisai</b>!');
}
actor.playedThisRound.push({name:card.name, icon:card.icon, badge:card.badge, blocked:blocked, desc:card.desc, exclusive:ENEMY_ONLY_IDS.has(card.id)});
if(blocked) return;
if(PERSISTENT.has(card.id)){
actor.tableSlotsUsed = (actor.tableSlotsUsed||0) + 1;
}
if(SELF_DRAW_IDS.has(card.id) && actor.silenced){
log('sys', actorLabel+' dibungkam dan tidak bisa menarik kartu apa pun.');
return;
}
if(card.id==='loveEnemy' && opp.silenced){
log('sys', oppLabel+' dibungkam, tidak bisa menerima kartu apa pun.');
return;
}
switch(card.id){
case 'draw3': case 'draw5': case 'draw7': case 'draw9': {
const n = card.num;
if(removeValue(S.deck, n)){
actor.visible.push(n);
log('sys', 'Kartu bernilai '+n+' ditarik dari dek.');
} else {
log('sys', 'Kartu bernilai '+n+' sudah tidak ada di dek. Tidak terjadi apa-apa.');
}
break;
}
case 'perfect': {
const cur = trueTotal(actorKey);
const v = bestValueFor(cur, S.deck, S.target);
if(v===null){ log('sys','Dek kosong, tidak ada kartu yang bisa ditarik.'); break; }
removeValue(S.deck, v);
actor.visible.push(v);
log('sys', actorLabel+' menarik kartu terbaik: '+v+'.');
break;
}
case 'destroy': {
if(opp.visible.length>0){
const v = opp.visible.pop();
S.deck.push(v);
log('sys', 'Kartu terbuka '+oppLabel+' bernilai '+v+' dikembalikan ke dek.');
} else {
log('sys', 'Tidak ada kartu terbuka lawan untuk dihancurkan.');
}
break;
}
case 'return': {
if(actor.visible.length>0){
const v = actor.visible.pop();
S.deck.push(v);
log('sys', actorLabel+' mengembalikan kartu bernilai '+v+' ke dek.');
} else {
log('sys', 'Tidak ada kartu terbuka sendiri untuk ditarik ulang.');
}
break;
}
case 'exchange': {
if(actor.visible.length>0 && opp.visible.length>0){
const ai = actor.visible.length-1, oi = opp.visible.length-1;
const tmp = actor.visible[ai];
actor.visible[ai] = opp.visible[oi];
opp.visible[oi] = tmp;
log('sys', 'Kartu terakhir ditukar antara kedua pihak.');
} else {
log('sys', 'Pertukaran gagal &mdash; salah satu pihak belum punya kartu terbuka.');
}
break;
}
case 'goFor17': S.target=17; log('sys','Target ronde berubah menjadi <b>17</b>.'); break;
case 'goFor24': S.target=24; log('sys','Target ronde berubah menjadi <b>24</b>.'); break;
case 'goFor27': S.target=27; log('sys','Target ronde berubah menjadi <b>27</b>.'); break;
case 'oneUp': {
opp.betMod = (opp.betMod||0)+1;
log('sys', 'Jika '+oppLabel+' kalah ronde ini, kerugiannya bertambah.');
break;
}
case 'desperation': {
actor.noLossIfLose = true;
log('sys', actorLabel+' tidak akan kehilangan nyala meski kalah ronde ini.');
break;
}
case 'shield': {
actor.shieldActive = true;
log('sys', actorLabel+' kini terlindungi Perisai.');
break;
}
case 'loveEnemy': {
const curO = trueTotal(oppKey);
const v = bestValueFor(curO, S.deck, S.target);
if(v===null){ log('sys','Dek kosong, tidak ada yang bisa diberikan.'); break; }
removeValue(S.deck, v);
opp.visible.push(v);
log('sys', oppLabel+' menerima kartu bernilai '+v+' dari kebaikan (atau jebakan) ini.');
break;
}
case 'trumpSwitch': {
const toDiscard = Math.min(2, actor.specials.length);
for(let i=0;i<toDiscard;i++){
const idx = Math.floor(Math.random()*actor.specials.length);
actor.specials.splice(idx,1);
}
const pool = actorKey==='player' ? CARD_POOL : opponentCardPool();
for(let i=0;i<3;i++){
if(actor.specials.length<HAND_CAP+3) actor.specials.push(drawSpecialInstance(pool, actor.specials));
}
log('sys', actorLabel+' mengocok ulang kartu spesialnya.');
break;
}
case 'remove': {
if(opp.specials.length>0){
const idx = Math.floor(Math.random()*opp.specials.length);
opp.specials.splice(idx,1);
log('sys', 'Satu kartu spesial '+oppLabel+' disita.');
} else {
log('sys', oppLabel==='kau' ? 'Kau tidak punya kartu spesial untuk disita.' : oppName()+' tidak punya kartu spesial untuk disita.');
}
break;
}
case 'deputyCurse': {
if(opp.specials.length>0){
const idx = Math.floor(Math.random()*opp.specials.length);
opp.specials.splice(idx,1);
}
if(S.deck.length>0){
const maxV = Math.max(...S.deck);
if(opp.silenced){
log('sys', oppLabel+' dibungkam, tidak bisa menerima kartu ini.');
} else {
removeValue(S.deck, maxV);
opp.visible.push(maxV);
log('sys', oppLabel+' dipaksa menarik kartu tertinggi: '+maxV+'.');
}
} else {
log('sys', 'Dek kosong, tidak ada kartu tertinggi yang bisa dipaksakan.');
}
break;
}
case 'demonGrip': {
const toDiscard = Math.ceil(actor.specials.length/2);
for(let i=0;i<toDiscard;i++){
if(actor.specials.length===0) break;
const idx = Math.floor(Math.random()*actor.specials.length);
actor.specials.splice(idx,1);
}
const cur = trueTotal(actorKey);
const v = bestValueFor(cur, S.deck, S.target);
if(v!==null){ removeValue(S.deck, v); actor.visible.push(v); }
opp.betMod = (opp.betMod||0) + 6;
log('sys', actorLabel+' mengorbankan separuh kartu spesialnya demi tarikan terbaik. Taruhan '+oppLabel+' naik drastis.');
break;
}
case 'kingsWill': {
log('sys', actorLabel+' mengibaskan tangan &mdash; seluruh ronde dibatalkan dan diulang dari awal!');
dealRound();
return;
}
case 'eternalSilence': {
opp.silenced = true;
log('sys', oppLabel+' kini dibungkam &mdash; tidak bisa menarik kartu sama sekali selama ronde ini.');
break;
}
}
}
const WIN_LINES = [
'Kau menang. Lilin '+'{opp}'+' meleleh lebih cepat.',
'Kartu berpihak padamu ronde ini. '+'{opp}'+' mundur selangkah, lilinnya makin pendek.',
'Kau menang telak. Ada keresahan sekilas di balik topeng '+'{opp}'+'.'
];
const LOSE_LINES = [
'Kau kalah. Lilinmu memendek.',
'Kartu tak berpihak kali ini. Nyala lilinmu meredup sedikit lagi.',
'Kau kalah ronde ini &mdash; rasa hangat di dadamu ikut memudar bersama lilin.'
];
const DRAW_LINES = [
'Seri! Tak ada nyala yang hilang kali ini.',
'Waktu seperti membeku sejenak. Tak ada yang menang, tak ada yang kalah &mdash; untuk sekarang.'
];
function fillName(s){ return s.replace('{opp}', oppName()); }
function enterSuspense(){
document.querySelectorAll('#player-cards .pcard:not(:first-child), #opp-cards .pcard:not(:first-child)').forEach(el=>el.classList.add('dim'));
document.querySelectorAll('.candle, #table-trumps, .specials-row, .total-tag, .life-num, .name-tag, .deck-count').forEach(el=>el.classList.add('dim'));
const pEl = document.getElementById('player-cards').firstElementChild;
const oEl = document.getElementById('opp-cards').firstElementChild;
if(pEl) pEl.classList.add('spotlight');
if(oEl) oEl.classList.add('spotlight');
}
function exitSuspense(){
document.querySelectorAll('.dim').forEach(el=>el.classList.remove('dim'));
document.querySelectorAll('.spotlight').forEach(el=>el.classList.remove('spotlight'));
}
function tensionDrone(){
if(!audioCtx || audioMuted) return;
const t0 = audioCtx.currentTime;
const osc = audioCtx.createOscillator();
const gain = audioCtx.createGain();
osc.type = 'sine';
osc.frequency.setValueAtTime(75, t0);
osc.frequency.linearRampToValueAtTime(155, t0+1.3);
gain.gain.setValueAtTime(0.0001, t0);
gain.gain.linearRampToValueAtTime(0.09, t0+0.3);
gain.gain.linearRampToValueAtTime(0.0001, t0+1.35);
osc.connect(gain); gain.connect(audioCtx.destination);
osc.start(t0); osc.stop(t0+1.4);
}
function revealAndResolve(){
const pEl = document.getElementById('player-cards').firstElementChild;
const oEl = document.getElementById('opp-cards').firstElementChild;
const _sid = sid;
duckMusic(true);
if(pEl){ pEl.classList.remove('spotlight'); pEl.classList.add('flip-anim-slow'); }
tone(520,0.16,'triangle',0.09,0.05);
setTimeout(()=>{
if(_sid!==sid) return;
if(pEl){ pEl.classList.remove('peek'); pEl.textContent = S.player.hidden; }
}, 380);
setTimeout(()=>{
if(_sid!==sid) return;
if(pEl) pEl.classList.remove('flip-anim-slow');
if(oEl){ oEl.classList.remove('spotlight'); oEl.classList.add('flip-anim-slow'); }
tone(420,0.18,'triangle',0.1,0.05);
}, 800);
setTimeout(()=>{
if(_sid!==sid) return;
if(oEl){ oEl.classList.remove('back'); oEl.textContent = S.opponent.hidden; }
}, 1180);
setTimeout(()=>{
if(_sid!==sid) return;
if(oEl) oEl.classList.remove('flip-anim-slow');
exitSuspense();
}, 1600);
setTimeout(()=>{ if(_sid===sid) resolveRound(); }, 2300);
}
function resolveRound(){
if(S.gameOver || S.awaitingContinue) return;
duckMusic(false);
const pTotal = trueTotal('player');
const oTotal = trueTotal('opponent');
const target = S.target;
const pBust = pTotal > target;
const oBust = oTotal > target;
const isDoubleBust = pBust && oBust;
let winner;
if(pBust && oBust) winner = pTotal===oTotal ? 'draw' : (pTotal<oTotal ? 'player':'opponent');
else if(pBust) winner = 'opponent';
else if(oBust) winner = 'player';
else winner = pTotal===oTotal ? 'draw' : (Math.abs(target-pTotal)<Math.abs(target-oTotal) ? 'player':'opponent');
let revealHtml = 'Kartu tertutupmu: <b>'+S.player.hidden+'</b> &middot; Kartu tertutup '+oppName()+': <b>'+S.opponent.hidden+'</b><br>'+
'Totalmu: <b>'+pTotal+'</b>'+(pBust?' (BUST)':'')+' &middot; Total '+oppName()+': <b>'+oTotal+'</b>'+(oBust?' (BUST)':'')+' &middot; Target: <b>'+target+'</b>';
const overlay = document.getElementById('overlay-round');
const titleEl = document.getElementById('overlay-title');
const revealEl = document.getElementById('overlay-reveal');
const oldPlayerLife = S.lives.player;
const oldOppLife = S.lives.opponent;
if(winner==='draw'){
titleEl.className = 'draw';
if(isDoubleBust){
titleEl.textContent = 'Seri yang Mengerikan';
revealHtml += '<br><i>Kalian berdua melewati batas dengan angka yang sama persis.</i>';
sfxDoubleBust(); flashFx('doublebust');
} else {
titleEl.textContent = 'Seri';
sfxDraw(); flashFx('draw');
}
log('sys', pick(DRAW_LINES));
} else if(winner==='player'){
const actualLoss = S.opponent.noLossIfLose ? 0 : Math.max(1,1+(S.opponent.betMod||0));
S.lives.opponent = Math.max(0, S.lives.opponent - actualLoss);
if(actualLoss>0) S.opponent.stageLossCount = (S.opponent.stageLossCount||0) + 1;
titleEl.className = isDoubleBust ? 'doublebust' : 'win';
titleEl.textContent = isDoubleBust ? 'Sama-sama Bust — Kau Menang Tipis' : 'Kau Menang Ronde Ini';
log('you', fillName(pick(WIN_LINES)));
if(isDoubleBust){ sfxDoubleBust(); flashFx('doublebust'); } else { sfxWinRound(); flashFx('win'); }
shakeTable();
impReact(true);
} else {
const actualLoss = (godModeImmune || S.player.noLossIfLose) ? 0 : Math.max(1,1+(S.player.betMod||0));
S.lives.player = Math.max(0, S.lives.player - actualLoss);
titleEl.className = isDoubleBust ? 'doublebust' : 'lose';
titleEl.textContent = isDoubleBust ? 'Sama-sama Bust — Kau Kalah Tipis' : 'Kau Kalah Ronde Ini';
log('opp', pick(LOSE_LINES));
if(godModeImmune) log('sys', '[GOD MODE] Kekebalan aktif -- nyala tidak berkurang.');
if(isDoubleBust){ sfxDoubleBust(); flashFx('doublebust'); } else { sfxLoseRound(); flashFx('lose'); }
shakeTable();
if(!godModeImmune) impReact(false);
}
revealEl.innerHTML = revealHtml;
S.awaitingContinue = true;
overlay.classList.remove('hidden');
renderCandles();
renderLives();
if(S.lives.player < oldPlayerLife) pulseCandleHit('candle-player');
if(S.lives.opponent < oldOppLife) pulseCandleHit('candle-opp');
}
function shakeTable(){
const t = document.querySelector('.table');
t.classList.add('shake');
setTimeout(()=>t.classList.remove('shake'), 420);
}
function beginCampaign(chosenDifficulty){
difficulty = chosenDifficulty;
stageIndex = 0;
carriedPlayerLife = MAX_LIFE_PLAYER;
totalRoundsPlayed = 0;
showStageTransition(false);
updateStageMusic();
}
function playDoorTransition(durationMs, callback){
const el = document.getElementById('door-transition');
el.classList.remove('hidden');
void el.offsetWidth;
el.classList.add('opening');
setTimeout(()=>{
el.classList.add('hidden');
el.classList.remove('opening');
if(callback) callback();
}, durationMs);
}
function sfxEpicFanfare(){
tone(523,0.3,'sine',0.16,0); tone(659,0.3,'sine',0.16,0.22); tone(784,0.3,'sine',0.16,0.44);
tone(1046,0.4,'sine',0.18,0.66); tone(1318,0.6,'sine',0.2,0.95);
}
function showStageTransition(showClearOfPrevious){
const ov = document.getElementById('overlay-stage');
const eyebrow = document.getElementById('stage-eyebrow');
const title = document.getElementById('stage-title');
const body = document.getElementById('stage-body');
const stage = STAGES[stageIndex];
eyebrow.textContent = 'TAHAP '+(stageIndex+1)+' / '+STAGES.length;
title.textContent = stage.name;
let html = '';
if(showClearOfPrevious && stageIndex>0){
html += '<p class="stage-clear-text">'+STAGES[stageIndex-1].clearWin+'</p>';
sfxStageClear();
}
html += '<p>'+stage.intro+'</p>';
body.innerHTML = html;
ov.classList.remove('hidden');
}
function endGame(result){
if(S) S.gameOver = true;
if(result==='win'){
playDoorTransition(2900, ()=>revealEndScreen('win'));
} else {
revealEndScreen('lose');
}
}
function revealEndScreen(result){
document.getElementById('screen-game').classList.remove('active');
const end = document.getElementById('screen-end');
end.classList.add('active');
const title = document.getElementById('end-title');
const msg = document.getElementById('end-message');
const note = document.getElementById('end-difficulty-note');
const stats = document.getElementById('end-stats');
const badge = document.getElementById('end-badge');
const galleryBtn = document.getElementById('btn-gallery');
badge.classList.add('hidden');
galleryBtn.classList.add('hidden');
note.textContent = '';
stats.textContent = '';
if(result==='win'){
title.textContent = 'Kau Bebas';
title.className = 'win';
msg.textContent = STAGES[STAGES.length-1].clearWin;
stats.innerHTML = 'Mode <b>'+DIFFICULTIES[difficulty].label+'</b> &middot; 5/5 tahap ditaklukkan &middot; '+totalRoundsPlayed+' ronde dimainkan';
if(difficulty==='sulit'){
badge.classList.remove('hidden');
note.textContent = 'Kau menyelesaikan seluruh perjalanan tanpa satu pun lilin dipulihkan antar tahap. Sungguh luar biasa.';
galleryBtn.classList.remove('hidden');
flashFx('win');
setTimeout(()=>flashFx('win'), 350);
setTimeout(()=>flashFx('win'), 750);
shakeTable();
sfxEpicFanfare();
} else if(difficulty==='normal'){
note.textContent = 'Selamat! Kau berhasil menaklukkan Mode Normal. Coba Mode Sulit kalau berani menguji dirimu lebih jauh.';
flashFx('win'); sfxTrueWin();
} else {
note.textContent = 'Selamat sudah menyelesaikan ceritanya. Kalau siap tantangan sesungguhnya, coba Mode Sulit.';
flashFx('win'); sfxTrueWin();
}
} else {
title.textContent = 'Lilinmu Padam';
title.className = 'lose';
msg.textContent = STAGES[stageIndex].loseText;
note.textContent = 'Kalah di '+STAGES[stageIndex].name+' (Tahap '+(stageIndex+1)+'/5) &middot; Mode '+DIFFICULTIES[difficulty].label+'.';
stats.innerHTML = 'Total ronde dimainkan sepanjang perjalanan ini: <b>'+totalRoundsPlayed+'</b>';
flashFx('lose'); sfxGameOverLose();
}
}
function setTurnLocked(locked){
document.getElementById('btn-hit').disabled = locked;
document.getElementById('btn-stand').disabled = locked;
}
function cardEl(value){
const d = document.createElement('div');
d.className = 'pcard';
d.textContent = value;
return d;
}
function backCardEl(){
const d = document.createElement('div');
d.className = 'pcard back';
return d;
}
function render(){
document.getElementById('tag-round').textContent = S.round;
const targetBig = document.getElementById('target-num-big');
const targetDisp = document.getElementById('target-display');
targetBig.textContent = S.target;
if(S.target !== 21) targetDisp.classList.add('changed');
else targetDisp.classList.remove('changed');
const pRow = document.getElementById('player-cards');
pRow.innerHTML = '';
const pHiddenCard = document.createElement('div');
pHiddenCard.className = 'pcard peek';
pHiddenCard.textContent = S.player.hidden;
pRow.appendChild(pHiddenCard);
S.player.visible.forEach(v=>pRow.appendChild(cardEl(v)));
const oRow = document.getElementById('opp-cards');
oRow.innerHTML = '';
oRow.appendChild(backCardEl());
S.opponent.visible.forEach(v=>oRow.appendChild(cardEl(v)));
const pTotal = trueTotal('player');
document.getElementById('player-total').textContent = pTotal;
document.getElementById('player-bust').style.display = pTotal > S.target ? 'inline' : 'none';
const oVis = visibleTotal('opponent');
document.getElementById('opp-total').textContent = oVis + ' (+ tertutup)';
document.getElementById('opp-bust').style.display = oVis > S.target ? 'inline' : 'none';
document.getElementById('deck-count').textContent = S.deck.length;
const ti = document.getElementById('turn-indicator');
if(S.turn==='player'){
ti.textContent = 'Giliranmu';
ti.className = 'turn-indicator';
} else if(S.turn==='opponent'){
ti.textContent = oppName()+' berpikir...';
ti.className = 'turn-indicator opp';
} else {
ti.textContent = 'Menghitung hasil ronde...';
ti.className = 'turn-indicator opp';
}
document.getElementById('opp-specials-count').textContent = 'Kartu spesial: '+S.opponent.specials.length;
document.getElementById('trumps-opp-label').textContent = oppName();
renderTableTrumps();
const psRow = document.getElementById('player-specials');
psRow.innerHTML = '';
if(S.player.specials.length===0){
const ghost = document.createElement('div');
ghost.className = 'special-card ghost';
ghost.innerHTML = '<div class="sc-name">Tak ada kartu</div><div class="sc-desc">Kartu spesial baru akan datang tiap ronde.</div>';
psRow.appendChild(ghost);
}
const tableFull = (S.player.tableSlotsUsed||0) >= TABLE_LIMIT;
S.player.specials.forEach(card=>{
const el = document.createElement('div');
const isPersistent = PERSISTENT.has(card.id);
const lockedByTurn = (S.turn!=='player') || S.awaitingContinue;
const lockedByTable = isPersistent && tableFull;
const locked = lockedByTurn || lockedByTable;
el.className = 'special-card'+(locked?' locked':'');
let badge = card.badge ? ' <b>'+card.badge+'</b>' : '';
let tableTag = lockedByTable ? '<div class="sc-full-tag">Meja penuh (5/5)</div>' : '';
el.innerHTML = iconSvg(card.icon)+'<div class="sc-name">'+card.name+badge+'</div><div class="sc-desc">'+card.desc+'</div>'+tableTag;
if(!locked){
el.addEventListener('click', ()=>playSpecialCard('player', card.uid));
}
psRow.appendChild(el);
});
document.getElementById('table-slots-tag').textContent = (S.player.tableSlotsUsed||0)+'/'+TABLE_LIMIT;
const hitBtn = document.getElementById('btn-hit');
const standBtn = document.getElementById('btn-stand');
const myTurn = S.turn==='player' && !S.awaitingContinue;
hitBtn.disabled = !myTurn || S.deck.length===0;
standBtn.disabled = !myTurn;
renderCandles();
renderLives();
}
function renderTableTrumps(){
const pRow = document.getElementById('trumps-player');
const oRow = document.getElementById('trumps-opp');
pRow.innerHTML = '';
oRow.innerHTML = '';
const makeChip = (c, side)=>{
const chip = document.createElement('div');
chip.className = 'trump-chip '+(side==='mine'?'mine':'theirs')+(c.blocked?' blocked':'')+(c.exclusive?' exclusive':'');
let badge = c.badge ? ' '+c.badge : '';
chip.innerHTML = iconSvg(c.icon)+'<span>'+c.name+badge+(c.blocked?' (diblokir)':'')+'</span>'+
'<div class="chip-tooltip">'+(c.desc||'')+'</div>';
chip.addEventListener('click', (e)=>{
e.stopPropagation();
const wasOpen = chip.classList.contains('tooltip-open');
document.querySelectorAll('.trump-chip.tooltip-open').forEach(el=>el.classList.remove('tooltip-open'));
if(!wasOpen) chip.classList.add('tooltip-open');
});
return chip;
};
(S.player.playedThisRound||[]).forEach(c=>pRow.appendChild(makeChip(c,'mine')));
(S.opponent.playedThisRound||[]).forEach(c=>oRow.appendChild(makeChip(c,'theirs')));
const bothEmpty = (S.player.playedThisRound||[]).length===0 && (S.opponent.playedThisRound||[]).length===0;
document.getElementById('table-trumps').style.display = bothEmpty ? 'none' : 'flex';
}
function renderLives(){
document.getElementById('life-player-num').textContent = S.lives.player;
document.getElementById('life-opp-num').textContent = S.lives.opponent;
}
function renderCandles(){
setCandle('wax-player', 'flame-pos-player', 'candle-player', S.lives.player, MAX_LIFE_PLAYER);
setCandle('wax-opp', 'flame-pos-opp', 'candle-opp', S.lives.opponent, STAGES[stageIndex].maxLife);
}
function setCandle(waxId, posId, svgId, life, maxLife){
const frac = Math.max(0, life/maxLife);
const maxH = 80, minH = 14;
const h = minH + (maxH-minH)*frac;
const bottom = 110;
const wax = document.getElementById(waxId);
wax.setAttribute('height', h);
wax.setAttribute('y', bottom-h);
document.getElementById(posId).style.transform = 'translateY('+(bottom-h-30)+'px)';
const svg = document.getElementById(svgId);
if(life<=0) svg.classList.add('dead'); else svg.classList.remove('dead');
}
function initialCandlePositions(){
setCandle('wax-player','flame-pos-player','candle-player', MAX_LIFE_PLAYER, MAX_LIFE_PLAYER);
setCandle('wax-opp','flame-pos-opp','candle-opp', STAGES[0].maxLife, STAGES[0].maxLife);
}