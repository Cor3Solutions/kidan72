/* KIDAN 72 — Home page */

Store.ready.then(() => {
  document.getElementById("bags").innerHTML = KIDAN.bags.map(bagCard).join("");
  setUpBrandFilm();
});

/* Brand film: plays on this page with only play/pause.
   - No YouTube title, channel name, logo or control bar is shown:
     the YouTube player is made taller than the frame, so the strips
     where YouTube puts its title and logo sit outside the visible area,
     and a clear layer on top catches taps, so YouTube's own buttons
     never appear.
   - Tap the film to play, tap again to pause.
   - Plays still count as views on the YouTube channel. */
function setUpBrandFilm() {
  const id = youtubeId(KIDAN.brandFilm);
  const box = document.getElementById("brand-film");
  if (!box) return;
  if (!id) { box.closest("section").hidden = true; return; }

  const btn = box.querySelector(".yt-film-play");
  const stage = box.querySelector(".yt-film-stage");
  let player = null;
  let started = false;

  function setState(state) {            // "idle" | "loading" | "playing" | "paused"
    box.dataset.state = state;
    btn.setAttribute("aria-label", state === "playing" ? "Pause the KIDAN 72 film" : "Play the KIDAN 72 film");
  }
  setState("idle");

  function loadApi() {
    if (window.YT && window.YT.Player) return Promise.resolve();
    return new Promise(resolve => {
      const prev = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = () => { if (prev) prev(); resolve(); };
      const tag = document.createElement("script");
      tag.src = "https://www.youtube.com/iframe_api";
      document.head.appendChild(tag);
    });
  }

  function start() {
    setState("loading");
    loadApi().then(() => {
      const holder = document.createElement("div");
      stage.appendChild(holder);
      player = new YT.Player(holder, {
        videoId: id,
        host: "https://www.youtube-nocookie.com",
        playerVars: {
          autoplay: 1, controls: 0, disablekb: 1, fs: 0, rel: 0,
          modestbranding: 1, iv_load_policy: 3, playsinline: 1
        },
        events: {
          onReady: e => e.target.playVideo(),
          onStateChange: e => {
            if (e.data === YT.PlayerState.PLAYING) { started = true; box.classList.remove("needs-tap"); setState("playing"); }
            else if (e.data === YT.PlayerState.PAUSED) setState("paused");
            else if (e.data === YT.PlayerState.ENDED) { player.seekTo(0, true); player.pauseVideo(); setState("paused"); }
          }
        }
      });
      // Some phones block playing until the video itself is tapped:
      // if it has not started after a few seconds, let the tap through.
      setTimeout(() => { if (!started) box.classList.add("needs-tap"); }, 3500);
    });
  }

  btn.addEventListener("click", () => {
    if (!player) { start(); return; }
    if (box.dataset.state === "playing") player.pauseVideo();
    else player.playVideo();
  });
}
