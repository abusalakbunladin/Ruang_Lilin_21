const TUTOR_OPPONENT = {
name: "Ki Sumbu Biru",
maxLife: 4
};
const TUTOR_SKILL = 0.2;
let TUTOR_HINTS_SHOWN = {
open: false,
reveal: false,
bust: false,
usedSpecial: false,
oppSpecial: false
};
let tutorRetryCount = 0;
function tutorResetHintFlags() {
TUTOR_HINTS_SHOWN = {
open: false,
reveal: false,
bust: false,
usedSpecial: false,
oppSpecial: false
};
tutorRetryCount = 0;
}
function showTutorNote(opts) {
const overlay = document.getElementById("overlay-tutor-note");
if (!overlay) return;
const figureEl = document.getElementById("tutor-note-figure");
if (figureEl && !figureEl.innerHTML && window.SPRITE_TUTOR) {
figureEl.innerHTML = window.SPRITE_TUTOR;
}
document.getElementById("tutor-note-eyebrow").textContent = opts.eyebrow || "KI SUMBU BIRU";
document.getElementById("tutor-note-body").innerHTML = opts.html || "";
const btnMain = document.getElementById("btn-tutor-note-main");
const btnSkip = document.getElementById("btn-tutor-note-skip");
btnMain.textContent = opts.mainLabel || "Mengerti";
btnMain.onclick = () => {
overlay.classList.add("hidden");
if (opts.onMain) opts.onMain();
};
if (opts.showSkip === false) {
btnSkip.classList.add("hidden");
btnSkip.onclick = null;
} else {
btnSkip.classList.remove("hidden");
btnSkip.textContent = opts.skipLabel || "Lewati Tutorial";
btnSkip.onclick = () => {
overlay.classList.add("hidden");
(opts.onSkip || showTutorialExitChoice)();
};
}
overlay.classList.remove("hidden");
}
function showTutorialExitChoice() {
showTutorNote({
eyebrow: "LEWATI TUTORIAL?",
html: '<p>&ldquo;Mau berhenti berlatih di sini?&rdquo; Ki Sumbu Biru mengangguk pelan. &ldquo;Boleh saja. Kau bisa langsung duduk di meja yang sesungguhnya sekarang juga, atau kembali dulu ke layar Mode Kampanye untuk meninjau tingkat kesulitan dan aturan sebelum melangkah lebih jauh.&rdquo;</p>',
mainLabel: "Langsung Main Kampanye",
skipLabel: "Kembali ke Menu Kampanye",
onMain: () => {
tutorialJumpToCampaign();
},
onSkip: () => {
exitTutorialToMenu();
}
});
}
function tutorialJumpToCampaign() {
markTutorialSeen();
if (S) S.gameOver = true;
["overlay-tutor-note", "overlay-round", "overlay-stage"].forEach(id => {
const el = document.getElementById(id);
if (el) el.classList.add("hidden");
});
document.getElementById("screen-tutorial").classList.remove("active");
document.getElementById("screen-start").classList.remove("active");
document.getElementById("screen-game").classList.add("active");
ensureAudio();
beginCampaign(tutorPendingDifficulty);
}
const TUTOR_OPEN_NOTES = [{
eyebrow: "SEBELUM MULAI",
html: '<p>Lilin di antara kalian berdua meredup sesaat, seolah menahan napas, lalu kembali menyala. &ldquo;Lihat mejamu,&rdquo; katanya. &ldquo;Kau dapat dua kartu: satu <b>tertutup</b> &mdash; cuma kau yang tahu nilainya &mdash; dan satu <b>terbuka</b> &mdash; kelihatan oleh kita berdua. Punyaku juga begitu; kau bisa lihat kartu terbukaku, tapi kartu tertutupku masih rahasia sampai ronde berakhir.&rdquo;</p><p>&ldquo;Targetnya <b>21</b>. Nanti kau akan menjumlahkan nilai semua kartumu &mdash; tertutup, terbuka, dan yang kau ambil sepanjang ronde. Paling dekat ke 21 tanpa melewatinya, menang. Kalau totalmu melewati 21, itu <b>bust</b> &mdash; hampir selalu berarti kalah, apa pun kartuku. Kalau kita berdua bust, yang totalnya paling rendah yang menang; sama persis, seri.&rdquo;</p>'
}, {
eyebrow: "KARTU SPESIAL",
html: '<p>&ldquo;Lihat kartu-kartu kecil di bawah sana &mdash; itu <b>kartu spesial</b>,&rdquo; katanya. &ldquo;Sentuh salah satu dulu untuk membaca efeknya sebelum dipakai. Kalau sudah yakin, klik untuk memainkannya. Boleh pakai lebih dari satu dalam giliran yang sama, kalau kau punya beberapa dan merasa perlu.&rdquo;</p><p>&ldquo;Begitu dipakai, kartu itu langsung habis dan tertinggal terlihat di meja sepanjang sisa ronde &mdash; jadi aku tahu apa yang sudah kau mainkan, dan kau tahu punyaku. Kartu berbahaya bertanda <b>Bisa ditangkal</b> bisa dipatahkan lebih dulu oleh kartu Perisai, kalau salah satu dari kita kebetulan memilikinya.&rdquo;</p>'
}, {
eyebrow: "GILIRANMU",
html: '<p>&ldquo;Sekarang giliranmu. Kalau masih ada kartu spesial yang ingin kau pakai, silakan &mdash; baru setelah itu pilih salah satu: <b>Ambil Kartu</b> menambah satu angka terbuka baru ke totalmu, atau <b>Bertahan</b> &mdash; tidak menambah apa-apa, giliran pindah ke aku.&rdquo;</p><p>&ldquo;Ronde berakhir begitu ada dua <b>Bertahan</b> berturut-turut, satu dariku dan satu darimu, di urutan mana pun. Jadi tenang saja &mdash; kau tidak akan pernah terjebak kalah hanya karena aku diam-diam mengubah keadaan tepat setelah kau bertahan.&rdquo;</p><p>&ldquo;Lihat lilin di kedua ujung meja &mdash; punyaku di atas, punyamu di bawah. Itu nyawa kita. Kalah satu ronde memendekkan lilin milik pihak yang kalah, satu nyala. Latihan ini selesai begitu salah satu lilin kita padam total.&rdquo;</p>'
}];
function queueTutorOpenNotes() {
if (TUTOR_HINTS_SHOWN.open) return;
let idx = 0;
function showNext() {
if (idx >= TUTOR_OPEN_NOTES.length) {
TUTOR_HINTS_SHOWN.open = true;
return;
}
const note = TUTOR_OPEN_NOTES[idx];
idx += 1;
showTutorNote({
eyebrow: note.eyebrow,
html: note.html,
mainLabel: idx >= TUTOR_OPEN_NOTES.length ? "Mengerti, ayo mulai" : "Lanjut",
onMain: showNext
});
}
showNext();
}
function beginInteractiveTutorial(diff) {
tutorPendingDifficulty = diff || tutorPendingDifficulty || "normal";
tutorResetHintFlags();
document.getElementById("screen-tutorial").classList.remove("active");
document.getElementById("screen-game").classList.add("active");
ensureAudio();
initTutorialMatch();
queueTutorOpenNotes();
}
const TUTOR_OPEN_LOG_LINES = ["Ki Sumbu Biru duduk di seberangmu, lilin birunya menyala tenang.", "Ki Sumbu Biru mengangguk singkat sambil menata ulang kartu di mejanya.", "Nyala biru di tangan Ki Sumbu Biru berkedip sekali, lalu kembali tenang saat kau duduk."];
function initTutorialMatch() {
sid += 1;
stageIndex = 0;
window.onlineMode = false;
S = {
isTutorial: true,
round: 0,
lives: {
player: 6,
opponent: TUTOR_OPPONENT.maxLife
},
player: {
hidden: null,
visible: [],
specials: []
},
opponent: {
hidden: null,
visible: [],
specials: [],
stageLossCount: 0,
desperateAnnounced: false
},
deck: [],
target: 21,
turn: "player",
history: [],
awaitingContinue: false,
gameOver: false
};
updateTutorHeader();
clearLog();
log("sys", pick(TUTOR_OPEN_LOG_LINES));
startRound(true);
}
function updateTutorHeader() {
document.getElementById("tag-stage-num").textContent = "LATIHAN";
document.getElementById("tag-stage-name").textContent = TUTOR_OPPONENT.name;
document.getElementById("opp-name-tag").textContent = TUTOR_OPPONENT.name;
renderStageDecor();
}
function tutorRevealNoteHtml(innerHtml) {
return '<p style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--line);color:#7ab0e0;font-style:italic">Ki Sumbu Biru: ' + innerHtml + '</p>';
}
const _tutorOriginalResolveRound = resolveRound;
resolveRound = function() {
_tutorOriginalResolveRound();
if (!(S && S.isTutorial)) return;
const reveal = document.getElementById("overlay-reveal");
if (!reveal) return;
if (!TUTOR_HINTS_SHOWN.reveal) {
TUTOR_HINTS_SHOWN.reveal = true;
reveal.insertAdjacentHTML("beforeend", tutorRevealNoteHtml('&ldquo;Barusan itulah cara satu ronde selesai &mdash; kartu tertutup kita dibuka bersamaan, lalu dibandingkan ke target. Lihat lilin di meja: yang baru saja kalah kehilangan satu nyala. Kalau lilinmu sendiri yang padam duluan nanti, jangan risau &mdash; itu bukan akhir latihan ini, cuma ajakan coba lagi. Latihan ini baru benar-benar selesai begitu lilinku yang padam total.&rdquo;'));
return;
}
if (trueTotal("player") > S.target && !TUTOR_HINTS_SHOWN.bust) {
TUTOR_HINTS_SHOWN.bust = true;
reveal.insertAdjacentHTML("beforeend", tutorRevealNoteHtml('&ldquo;Totalmu tadi jadi <b>' + trueTotal("player") + '</b> &mdash; lewat dari target ' + S.target + '. Itulah bust yang kumaksud di awal. Lain kali kalau kau mulai ragu apakah masih aman mengambil kartu lagi, <b>Bertahan</b> di angka yang sudah ada sering kali lebih bijak daripada memaksa mendekati ' + S.target + '.&rdquo;'));
} else if ((S.player.playedThisRound || []).length > 0 && !TUTOR_HINTS_SHOWN.usedSpecial) {
TUTOR_HINTS_SHOWN.usedSpecial = true;
reveal.insertAdjacentHTML("beforeend", tutorRevealNoteHtml('&ldquo;Kartu spesial yang kau mainkan tadi tetap tercatat di meja sepanjang ronde ini &mdash; lihat lagi kalau lupa efeknya. Jangan buru-buru menghabiskan semuanya sekaligus; jatah barumu terbatas tiap ronde, jadi ada baiknya menyimpan sebagian untuk saat keadaan benar-benar mendesak.&rdquo;'));
} else if ((S.opponent.playedThisRound || []).length > 0 && !TUTOR_HINTS_SHOWN.oppSpecial) {
TUTOR_HINTS_SHOWN.oppSpecial = true;
reveal.insertAdjacentHTML("beforeend", tutorRevealNoteHtml('&ldquo;Aku juga pegang kartu spesial, seperti yang barusan kau lihat. Lawan yang sesungguhnya nanti punya pilihan yang jauh lebih ganas dari punyaku &mdash; jangan lengah cuma karena aku memainkannya perlahan-lahan.&rdquo;'));
}
};
function tutorialHandleContinue() {
document.getElementById("overlay-round").classList.add("hidden");
S.awaitingContinue = false;
if (S.lives.opponent <= 0) {
showTutorialVictory();
} else if (S.lives.player <= 0) {
showTutorialRetry();
} else {
startRound(false);
}
}
function showTutorialVictory() {
markTutorialSeen();
showTutorNote({
eyebrow: "LILINKU PADAM",
html: '<p>Ki Sumbu Biru tersenyum tipis saat nyala birunya meredup. &ldquo;Bagus. Kau sudah paham betul caranya &mdash; lawan yang sesungguhnya tidak akan mengalah semudah aku.&rdquo;</p><p>&ldquo;Sisanya, biar pengalaman yang mengajarimu. Waktunya duduk di meja yang sesungguhnya &mdash; atau kalau masih ingin meninjau dulu tingkat kesulitan dan aturan, kembalilah dulu ke layar Mode Kampanye.&rdquo;</p>',
mainLabel: "Mulai Mode Kampanye",
skipLabel: "Kembali ke Menu Kampanye",
showSkip: true,
onMain: () => {
tutorialJumpToCampaign();
},
onSkip: () => {
exitTutorialToMenu();
}
});
}
const TUTOR_RETRY_LINES = ['<p>&ldquo;Lilinmu padam duluan,&rdquo; katanya, tidak terdengar kecewa. &ldquo;Wajar &mdash; bahkan tawanan pertama butuh beberapa kali coba. Ayo ulang, kali ini perhatikan baik-baik pola yang membuatmu kalah.&rdquo;</p>', '<p>&ldquo;Padam lagi,&rdquo; katanya, tanpa nada menghakimi sama sekali. &ldquo;Tidak apa-apa. Coba ingat-ingat lagi momen tepat sebelum lilinmu meredup &mdash; di situ biasanya jawabannya ada, bukan di ronde-ronde sebelumnya.&rdquo;</p>', '<p>Ki Sumbu Biru menyalakan kembali lilinmu tanpa terburu-buru. &ldquo;Aku sudah menunggu di meja ini lebih lama dari yang bisa kau bayangkan. Beberapa kali coba lagi bukan apa-apa bagiku. Ambil napas dulu, lalu duduk kembali kalau sudah siap.&rdquo;</p>'];
function showTutorialRetry() {
const line = TUTOR_RETRY_LINES[Math.min(tutorRetryCount, TUTOR_RETRY_LINES.length - 1)];
tutorRetryCount += 1;
showTutorNote({
eyebrow: "BELUM APA-APA",
html: line,
mainLabel: "Coba Lagi",
onMain: () => {
initTutorialMatch();
}
});
}
function exitTutorialToMenu() {
markTutorialSeen();
if (S) S.gameOver = true;
["overlay-tutor-note", "overlay-round", "overlay-stage"].forEach(id => {
const el = document.getElementById(id);
if (el) el.classList.add("hidden");
});
document.getElementById("screen-tutorial").classList.remove("active");
document.getElementById("screen-game").classList.remove("active");
document.getElementById("screen-start").classList.add("active");
try {
document.getElementById("bgm-nonboss").pause();
} catch (e) {}
try {
document.getElementById("bgm-boss").pause();
} catch (e) {}
}
