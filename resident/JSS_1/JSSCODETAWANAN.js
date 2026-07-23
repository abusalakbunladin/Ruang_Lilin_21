function tutorialSeenBefore(){
try{ return localStorage.getItem('ruanglilin_tutorial_seen') === '1'; }
catch(e){ return false; }
}
function markTutorialSeen(){
try{ localStorage.setItem('ruanglilin_tutorial_seen', '1'); }catch(e){}
}
const TUTORIAL_STEPS = [
{
eyebrow: 'SEBUAH PENAMPAKAN',
html: '<p>Sebelum kau duduk di meja itu untuk pertama kalinya, sesuatu berkedip di sudut matamu. Sebuah nyala biru, dingin, tenang. Ketika kau menoleh, seorang lelaki tua sudah duduk di kursi kosong sebelahmu &mdash; lilin di tangannya menyala biru, bukan jingga seperti nyala yang lain di ruangan ini.</p>'+
'<p>&ldquo;Duduklah sebentar,&rdquo; katanya, suaranya lebih lembut dari yang kau kira. &ldquo;Namaku sudah lama tak penting lagi. Panggil saja aku <b>Ki Sumbu Biru</b>. Aku sudah duduk di meja ini lebih lama dari siapa pun yang pernah kau temui &mdash; cukup lama sampai nyalaku berubah warna. Sebelum permainan sesungguhnya dimulai, biar kujelaskan semuanya baik-baik. Kau tak perlu bingung nanti.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 1: TUJUANNYA APA',
html: '<p>&ldquo;Aturannya sebenarnya sederhana, meski taruhannya tidak. Kau dan lawanmu sama-sama mengumpulkan angka dari kartu. Siapa pun yang paling dekat dengan <b>angka target</b> &mdash; biasanya 21 &mdash; tanpa melewatinya, dialah yang menang ronde itu.&rdquo;</p>'+
'<p>&ldquo;Ingat baik-baik: dekat itu bagus, tapi <b>lewat itu bencana</b>. Kalau totalmu melebihi target, itu namanya <b>BUST</b> &mdash; dan bust hampir selalu berarti kalah, apa pun kartu lawanmu.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 2: KARTU DI DEK',
html: '<p>&ldquo;Di atas meja ada satu dek berisi sebelas kartu, bernilai 1 sampai 11 &mdash; masing-masing cuma satu, tidak ada kembarannya. Kalian berdua memakai dek yang <b>sama</b>. Begitu kartu &lsquo;delapan&rsquo; diambil salah satu dari kalian, tak ada &lsquo;delapan&rsquo; lagi sampai ronde berikutnya dimulai dan dek dikocok ulang.&rdquo;</p>'+
'<p>&ldquo;Itu artinya kau bisa berhitung &mdash; kartu apa yang sudah keluar, kartu apa yang mungkin masih tersisa. Perhatikan baik-baik, itu bisa menyelamatkan nyalamu.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 3: KARTU AWAL',
html: '<p>&ldquo;Begitu ronde dimulai, kalian berdua langsung menerima dua kartu. Satu kartu <b>tertutup</b> &mdash; hanya kau yang tahu nilainya sendiri, lawanmu tidak tahu. Satu lagi <b>terbuka</b> &mdash; semua orang bisa melihatnya, termasuk kau melihat kartu terbuka milik lawanmu.&rdquo;</p>'+
'<p>&ldquo;Jadi ingat ini: kau selalu tahu totalmu sendiri secara pasti. Tapi total lawanmu? Kau cuma tahu <b>sebagian</b> &mdash; kartu terbukanya saja. Kartu tertutup lawan tetap misteri sampai ronde itu berakhir.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 4: GILIRAN & AKHIR RONDE',
html: '<p>&ldquo;Kalian bergiliran. Di giliranmu, kau boleh memainkan kartu spesial sebanyak yang kau mau &mdash; tidak ada batasnya per giliran. Tapi cepat atau lambat kau harus memilih satu dari dua hal: <b>Ambil Kartu</b> (menambah satu kartu terbuka baru ke totalmu), atau <b>Bertahan</b> (tidak menambah apa-apa, giliran berpindah ke lawan).&rdquo;</p>'+
'<p>&ldquo;Ronde baru berakhir kalau <b>kedua pihak bertahan dua kali berturut-turut</b> tanpa ada yang mengambil kartu atau memainkan kartu spesial di antaranya. Kalau ada yang mengubah keadaan &mdash; misalnya mengambil kartu atau memakai kartu spesial &mdash; hitungan itu dimulai ulang dari nol. Jadi kau tidak akan pernah terjebak kalah hanya karena lawanmu diam-diam mengubah keadaan tepat setelah kau bertahan. Kau akan selalu dapat giliran untuk bereaksi.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 5: KARTU SPESIAL',
html: '<p>&ldquo;Sekarang soal kartu spesial. Ini yang membuat permainan ini berbeda dari sekadar hitung-hitungan biasa. Tiap ronde, kau dan lawanmu sama-sama mendapat kartu spesial baru &mdash; efeknya macam-macam: menarik kartu tertentu dari dek, mengubah angka target, menghancurkan kartu terbuka lawan, melindungimu dari efek jahat, dan banyak lagi. Cukup arahkan jarimu ke kartu itu untuk membaca efeknya sebelum dipakai.&rdquo;</p>'+
'<p>&ldquo;Tanganmu bisa menyimpan sampai <b>sepuluh</b> kartu spesial sekaligus. Tapi hati-hati: kartu yang efeknya bertahan lama di atas meja &mdash; seperti mengubah target atau menaikkan taruhan &mdash; itu cuma boleh ada <b>lima</b> di meja secara bersamaan. Kalau sudah penuh, kau masih bisa pakai kartu yang efeknya instan sampai ada ruang lagi.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 6: HASIL RONDE & NYAWA',
html: '<p>&ldquo;Begitu ronde selesai, kartu tertutup kalian berdua dibuka bersamaan. Totalnya dihitung, dibandingkan dengan target. Siapa pun yang paling dekat tanpa melewati, menang. Kalau kalian berdua melewati target, yang totalnya paling <b>rendah</b> yang menang. Dan kalau totalnya sama persis, itu <b>seri</b> &mdash; tak ada yang menang atau kalah.&rdquo;</p>'+
'<p>&ldquo;Nyawa kalian digambarkan sebagai lilin di meja ini. Kalah satu ronde, lilinmu memendek satu nyala. Kalau lilinmu padam total, kau kalah di tahap itu. Sederhana, kan? Tapi jangan anggap enteng &mdash; beberapa kartu spesial bisa membuat kekalahan atau kemenangan bernilai lebih dari satu nyala sekaligus.&rdquo;</p>'
},
{
eyebrow: 'ATURAN 7: PERJALANANMU',
html: '<p>&ldquo;Ruangan ini bukan satu-satunya. Ada <b>lima tahap</b> yang harus kau lewati, masing-masing dengan lawan yang lebih kuat dari sebelumnya: Sang Tawanan, Sang Penjaga, Sang Sekretaris, Wakil Raja Iblis, dan akhirnya&hellip; Raja Iblis sendiri. Menang satu tahap, kau lanjut ke tahap berikutnya. Kalah, permainanmu berakhir di situ.&rdquo;</p>'+
'<p>&ldquo;Satu hal lagi &mdash; pilih tingkat kesulitanmu baik-baik. Di <b>Mode Bayi</b>, nyawamu pulih penuh tiap naik tahap. Di <b>Normal</b>, cuma pulih sedikit. Di <b>Sulit</b>&hellip; nyawamu tidak pulih sama sekali dari awal sampai akhir. Pilih sesuai seberapa berani kau malam ini.&rdquo;</p>'
},
{
eyebrow: 'SELAMAT TINGGAL',
html: '<p>&ldquo;Itu saja yang perlu kau tahu. Sisanya, biar pengalaman yang mengajarimu.&rdquo;</p>'+
'<p>Lelaki tua itu tersenyum tipis, dan sebelum kau sempat bertanya lebih jauh, nyala birunya meredup dan menghilang &mdash; seolah dia tak pernah benar-benar ada di sana. Kursi di sebelahmu kembali kosong.</p>'+
'<p><i>Waktunya duduk di meja yang sesungguhnya.</i></p>'
}
];
let tutorStepIdx = 0;
let tutorPendingDifficulty = 'normal';
function renderTutorStep(){
const step = TUTORIAL_STEPS[tutorStepIdx];
document.getElementById('tutor-eyebrow').textContent = step.eyebrow;
document.getElementById('tutor-dialogue').innerHTML = step.html;
const prog = document.getElementById('tutor-progress');
prog.innerHTML = '';
TUTORIAL_STEPS.forEach((s,i)=>{
const dot = document.createElement('div');
dot.className = 'dot'+(i===tutorStepIdx?' active':(i<tutorStepIdx?' done':''));
prog.appendChild(dot);
});
const nextBtn = document.getElementById('btn-tutor-next');
nextBtn.textContent = (tutorStepIdx === TUTORIAL_STEPS.length-1) ? 'Mulai Permainan' : 'Lanjut';
}
function startTutorial(chosenDifficulty){
tutorPendingDifficulty = chosenDifficulty;
tutorStepIdx = 0;
document.getElementById('screen-start').classList.remove('active');
document.getElementById('screen-tutorial').classList.add('active');
renderTutorStep();
}
function finishTutorial(){
markTutorialSeen();
document.getElementById('screen-tutorial').classList.remove('active');
document.getElementById('screen-game').classList.add('active');
ensureAudio();
beginCampaign(tutorPendingDifficulty);
}
const IMP_CHEER_LINES = [
'Para imp bersorak riang melihat rajanya menang.',
'Tawa mengejek para imp bergema di ruangan tahta.',
'Imp-imp kecil menari-nari merayakan lilinmu yang meredup.'
];
const IMP_TAUNT_LINES = [
'Salah satu imp mendesis, "Cuma keberuntungan, manusia!"',
'Para imp terdiam sesaat, lalu berbisik gelisah pada rajanya.',
'Seekor imp melempar tulang kecil ke arahmu sambil mendecih kesal.'
];
function impReact(playerWon){
if(stageIndex !== STAGES.length-1) return;
const impRow = document.getElementById('imp-row');
if(!impRow.classList.contains('active')) return;
impRow.classList.remove('cheer','boo');
void impRow.offsetWidth;
if(playerWon){
impRow.classList.add('boo');
log('sys', pick(IMP_TAUNT_LINES));
} else {
impRow.classList.add('cheer');
log('sys', pick(IMP_CHEER_LINES));
}
setTimeout(()=>impRow.classList.remove('cheer','boo'), 1600);
}
function playSpecialCard(actorKey, cardUid){
if(S.gameOver || S.awaitingContinue) return;
if(S.turn !== actorKey) return;
const arr = S[actorKey].specials;
const idx = arr.findIndex(c=>c.uid===cardUid);
if(idx<0) return;
const card = arr[idx];
if(PERSISTENT.has(card.id) && (S[actorKey].tableSlotsUsed||0) >= TABLE_LIMIT) return;
arr.splice(idx,1);
applySpecial(actorKey, card);
render();
}
function doHit(actorKey){
if(S.gameOver) return;
if(S[actorKey].silenced){
log('sys', (actorKey==='player'?'Kau':oppName())+' dibungkam dan tidak bisa menarik kartu. Giliran otomatis bertahan.');
return doStand(actorKey);
}
if(S.deck.length===0){
log('sys', 'Dek kosong &mdash; '+(actorKey==='player'?'kau':oppName())+' terpaksa bertahan.');
return doStand(actorKey);
}
const v = popRandom(S.deck);
S[actorKey].visible.push(v);
log(actorKey==='player'?'you':'opp', (actorKey==='player'?'Kau':oppName())+' mengambil kartu bernilai <b>'+v+'</b>.');
sfxHit();
S.history.push({actor:actorKey, action:'hit'});
afterAction(actorKey);
}
function doStand(actorKey){
if(S.gameOver) return;
log(actorKey==='player'?'you':'opp', (actorKey==='player'?'Kau':oppName())+' bertahan.');
S.history.push({actor:actorKey, action:'stand'});
afterAction(actorKey);
}
function afterAction(actorKey){
if(S.gameOver) return;
const h = S.history;
const n = h.length;
if(n>=2 && h[n-1].action==='stand' && h[n-2].action==='stand'){
S.turn = null; 
render();
enterSuspense();
tensionDrone();
const _sid = sid;
setTimeout(()=>{ if(_sid===sid) revealAndResolve(); }, 1400);
return;
}
S.turn = actorKey==='player' ? 'opponent' : 'player';
render();
if(S.turn==='opponent'){
setTurnLocked(true);
const _sid2 = sid;
setTimeout(()=>{ if(_sid2===sid) aiTurn(); }, 900);
} else {
setTurnLocked(false);
}
}
const GALLERY_HISTORY_TEXT = "21: Ruang Lilin dimulai dari satu pertanyaan sederhana: bagaimana rasanya bertaruh nyawa lewat permainan kartu? Konsepnya lahir dari kekaguman pada mekanik kartu trump ala gim horor survival klasik, lalu dibentuk ulang jadi cerita sendiri &mdash; lima tahap, lima kepribadian, satu ruangan lilin yang tak pernah benar-benar tenang. Setiap kartu, animasi, dan keputusan AI di sini lahir dari berkali-kali dimainkan, dikomplain oleh penguji, dan diperbaiki lagi. Kamu baru saja menyelesaikan versi yang sudah melalui banyak sekali revisi itu &mdash; dengan cara paling sulit pula.";
const GALLERY_FUTURE_TEXT = "Beberapa ide yang masih mengambang: tahap keenam yang lebih gelap dari Raja Iblis sekalipun, mode Endless untuk yang merasa lima tahap terlalu singkat, kartu spesial baru yang bisa diracik sendiri, dan mungkin &mdash; kalau ada waktu &mdash; cerita alternatif dari sudut pandang Sang Tawanan. Belum ada janji pasti, tapi semuanya sudah tercatat.";
function cardChipHtml(c, exclusive){
return '<div class="gallery-card'+(exclusive?' exclusive':'')+'">'+iconSvg(c.icon)+'<div class="gc-name">'+c.name+(c.badge?' '+c.badge:'')+'</div><div class="gc-desc">'+c.desc+'</div></div>';
}
function renderGalleryTab(tab){
const content = document.getElementById('gallery-content');
if(tab==='numbers'){
let html = '<div class="gallery-grid">';
for(let n=1;n<=11;n++){ html += '<div class="gallery-num-card">'+n+'</div>'; }
html += '</div><p style="margin-top:14px;">Sebelas kartu angka ini dipakai bersama tiap ronde &mdash; 1 sampai 11, tanpa duplikat, dikocok ulang setiap kali ronde baru dimulai.</p>';
content.innerHTML = html;
} else if(tab==='trumps'){
let html = '<div class="gallery-grid">';
CARD_POOL.forEach(c=>{ html += cardChipHtml(c, false); });
const shown = new Set();
[3,4].forEach(idx=>{
(ENEMY_EXCLUSIVE[idx]||[]).forEach(c=>{
if(shown.has(c.id)) return;
shown.add(c.id);
html += cardChipHtml(c, true);
});
});
html += '</div>';
content.innerHTML = html;
} else if(tab==='history'){
content.innerHTML = '<h3>Sejarah Pembuatan</h3><p>'+GALLERY_HISTORY_TEXT+'</p>';
} else if(tab==='future'){
content.innerHTML = '<h3>Rencana Masa Depan</h3><p>'+GALLERY_FUTURE_TEXT+'</p>';
}
}
const GOD_CODE_HASH = 3660571160;
const GOD_CODE_LEN = 8;
let godKeyBuffer = '';
function simpleHash(str){
let hash = 5381;
for(let i=0;i<str.length;i++){
hash = ((hash*33) + str.charCodeAt(i)) >>> 0;
}
return hash;
}
function toggleGodMode(){
godModeActive = !godModeActive;
const panel = document.getElementById('godmode-panel');
if(godModeActive){
panel.classList.remove('hidden');
log('sys', '[GOD MODE] Diaktifkan.');
} else {
panel.classList.add('hidden');
godModeImmune = false;
const chk = document.getElementById('god-immune');
if(chk) chk.checked = false;
}
}
function godEnsureGameRunning(){
if(!S){
beginCampaign(difficulty || 'normal');
return false; 
}
return true;
}
function godInstantRoundResult(winner){
if(!godEnsureGameRunning()) return;
if(S.gameOver) return;
if(winner==='player'){
S.lives.opponent = Math.max(0, S.lives.opponent - 1);
log('sys', '[GOD MODE] Ronde dimenangkan secara instan.');
} else {
if(!godModeImmune){
S.lives.player = Math.max(0, S.lives.player - 1);
}
log('sys', '[GOD MODE] Ronde dikalahkan secara instan.');
}
render();
godResolveProgression();
}
function godResolveProgression(){
if(S.lives.player<=0){
endGame('lose');
} else if(S.lives.opponent<=0){
if(stageIndex >= STAGES.length-1){
endGame('win');
} else {
carriedPlayerLife = S.lives.player;
stageIndex += 1;
if(stageIndex === STAGES.length-1){
playDoorTransition(2900, ()=>showStageTransition(true));
} else {
showStageTransition(true);
}
}
}
}
function godJumpToStage(idx){
if(idx<0 || idx>=STAGES.length) return;
if(!S){ beginCampaign(difficulty || 'normal'); }
carriedPlayerLife = MAX_LIFE_PLAYER;
stageIndex = idx;
document.getElementById('overlay-round').classList.add('hidden');
document.getElementById('overlay-stage').classList.add('hidden');
document.getElementById('screen-end').classList.remove('active');
document.getElementById('screen-gallery').classList.remove('active');
document.getElementById('screen-start').classList.remove('active');
document.getElementById('screen-game').classList.add('active');
if(idx === STAGES.length-1){
playDoorTransition(2900, ()=>showStageTransition(false));
} else {
showStageTransition(false);
}
}
function godForceGameEnd(result){
if(result==='win'){ stageIndex = STAGES.length-1; }
endGame(result);
}