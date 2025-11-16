<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inheritance Databank Dashboard</title>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    /* ---------- RESET ---------- */
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; background:#f4f6f9; color:#222; line-height:1.5; }

    /* ---------- HEADER ---------- */
    header {
      background:#2c3e50; color:#fff; padding:14px 24px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:1000;
    }
    .logo img { height:50px; }
    nav a { color:#fff; text-decoration:none; margin:0 10px; font-weight:600; font-size:14px; }
    nav a:hover { color:#f1c40f; }

    /* ---------- HERO ---------- */
    .hero { background:#ecf0f1; padding:56px 20px; text-align:center; }
    .hero h1 { font-size:28px; color:#2c3e50; margin-bottom:8px; }
    .hero p { font-size:16px; color:#444; }

    /* ---------- SECTIONS ---------- */
    section { max-width:1200px; margin:48px auto; padding:0 18px; }
    h2 { text-align:center; color:#2c3e50; margin-bottom:22px; font-size:22px; }

    /* ---------- WHO SHOULD USE ---------- */
    .features {
      display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px;
    }
    .feature-box {
      display:block; background:#fff; border-radius:10px; overflow:hidden;
      text-decoration:none; color:inherit;
      box-shadow:0 6px 16px rgba(16,24,40,0.06);
      transition:transform .22s ease,box-shadow .22s ease;
    }
    .feature-box:hover { transform:translateY(-6px); box-shadow:0 12px 30px rgba(16,24,40,0.08); }
    .feature-img { width:100%; height:160px; object-fit:cover; display:block; }
    .feature-title { padding:14px 16px; font-size:18px; text-align:center; color:#1f2937; }

    /* ---------- PROGRAMS CAROUSEL ---------- */
    .carousel-wrapper { max-width:1000px; margin:0 auto; position:relative; margin-bottom:48px; }
    .carousel-viewport { overflow:hidden; border-radius:10px; }
    .carousel-track { display:flex; transition:transform .6s ease; }
    .slide { flex:0 0 100%; display:flex; gap:18px; align-items:stretch; justify-content:center; padding:12px; }
    .program-card { background:#fff; border-radius:10px; box-shadow:0 6px 18px rgba(16,24,40,0.06); padding:0; overflow:hidden; flex:1 1 0; max-width:480px; text-align:center; }
    .program-card img { width:100%; height:220px; object-fit:cover; display:block; }
    .program-card h3 { padding:14px 12px; font-size:18px; color:#1f2937; }

    @media (max-width:767px){
      .slide { flex-direction:column; }
      .program-card img { height:180px; }
    }

    /* ---------- COLLABORATORS ---------- */
    .collaborators-grid {
      display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr));
      gap:20px; justify-items:center; align-items:center;
    }
    .collab img { width:80px; height:80px; object-fit:contain; margin-bottom:10px; }
    .collab p { text-align:center; font-weight:600; }

    /* ---------- TESTIMONIALS ---------- */
    .testimonials-grid {
      display:grid; grid-template-columns:repeat(auto-fit, minmax(250px,1fr));
      gap:20px; justify-items:center;
    }
    .testimonial img { width:80px; height:80px; border-radius:50%; object-fit:cover; margin-bottom:10px; }
    .testimonial p { font-style:italic; color:#374151; font-size:16px; }
    .testimonial span { display:block; margin-top:10px; font-weight:600; color:#1f2937; }

    /* ---------- FOOTER ---------- */
    footer { background:#2c3e50; color:#fff; padding:32px 18px; margin-top:40px; }
    .footer-links, .footer-social { display:flex; gap:18px; justify-content:center; flex-wrap:wrap; margin-bottom:10px; }
    .footer-links a, .footer-social a { color:#fff; text-decoration:none; }
    .footer-social a { font-size:20px; }
    .footer-note { text-align:center; margin-top:8px; color:#e6e6e6; font-size:14px; }

  </style>
</head>
<body>

<!-- HEADER -->
<header>
  <?php
include 'db_connection.php';
$logoQuery = $conn->query("SELECT logo_path FROM settings WHERE id = 1");
$logoRow = $logoQuery->fetch_assoc();
$logoPath = !empty($logoRow['logo_path']) ? $logoRow['logo_path'] : 'default-logo.png';
?>
<img src="<?php echo $logoPath; ?>" alt="Company Logo" height="60">
  </a>
  <nav>
    <a href="#">Home</a>
    <a href="#">About</a>
    <a href="#programs">Programs</a>
    <a href="#who">Who Should Use</a>
    <a href="#collabs">Collaborators</a>
    <a href="#testimonials">Testimonials</a>
    <a href="login.php">Login/Signup</a>
    <a href="#">Contact</a>
  </nav>
</header>

<!-- HERO -->
<div class="hero">
  <h1>Welcome to the Inheritance Databank!</h1>
  <p>
    Manage inheritance securely, transparently, and efficiently. 
    Our platform ensures that your assets are safely stored and released to beneficiaries under the right conditions.
  </p>
</div>
<!-- WHO SHOULD USE -->
<section id="who">
  <h2>Who Should Use This System?</h2>
  <div class="features">
    <?php
    include 'db_connection.php';
    $result = $conn->query("SELECT * FROM system_sections");
    while ($row = $result->fetch_assoc()):
    ?>
      <a class="feature-box" href="login.php?type=<?php echo $row['section_key']; ?>">
        <img class="feature-img" src="<?php echo $row['image_path']; ?>" 
             alt="<?php echo htmlspecialchars($row['title']); ?>" 
             onerror="this.src='assets/placeholder.jpg'">
        <div class="feature-title"><?php echo htmlspecialchars($row['title']); ?></div>
        <p class="feature-desc"><?php echo htmlspecialchars($row['description']); ?></p>
      </a>
    <?php endwhile; ?>
  </div>
</section>

<!-- PROGRAMS CAROUSEL -->
<section id="programs">
  <h2>New Programs Available</h2>
  <div class="carousel-wrapper">
    <div class="carousel-viewport">
      <div class="carousel-track" id="programCarousel" aria-live="polite"></div>
    </div>
  </div>
</section>

<!-- COLLABORATORS -->
<section id="collabs">
  <h2>Our Collaborators</h2>
  <div class="collaborators-grid" id="collabCarousel"></div>
</section>

<!-- TESTIMONIALS -->
<section id="testimonials">
  <h2>Testimonials</h2>
  <div class="testimonials-grid" id="testimonialCarousel"></div>
</section>

<!-- FOOTER -->
<footer>
  <div class="footer-links">
    <a href="#">Home</a>
    <a href="#programs">Programs</a>
    <a href="#who">Who Should Use</a>
    <a href="#collabs">Collaborators</a>
    <a href="#testimonials">Testimonials</a>
    <a href="mailto:support@risa.example">Support</a>
  </div>
  <div class="footer-social">
    <a aria-label="facebook" href="#"><i class="fab fa-facebook"></i></a>
    <a aria-label="twitter" href="#"><i class="fab fa-twitter"></i></a>
    <a aria-label="linkedin" href="#"><i class="fab fa-linkedin"></i></a>
    <a aria-label="youtube" href="#"><i class="fab fa-youtube"></i></a>
  </div>
  <div class="footer-note">&copy; <?php echo date('Y'); ?> Inheritance Databank. All rights reserved.</div>
</footer>

<!-- ---------- JS: Programs, Collaborators & Testimonials ---------- -->
<script>
(function(){
  // Programs
  const programs = [
    { img:'assets/image/estate.jpeg', title:'Estate Planning' },
    { img:'assets/image/will.jpeg', title:'Will Registration' },
    { img:'assets/image/assets.jpeg', title:'Asset Transfer' },
    { img:'assets/image/legal.jpeg', title:'Legal Advisory' },
    { img:'assets/image/dispute.jpeg', title:'Inheritance Dispute Resolution' }
  ];
  const programTrack = document.getElementById('programCarousel');

  function buildCarousel(track, items, perSlide) {
    track.innerHTML='';
    const slides=[];
    for(let i=0;i<items.length;i+=perSlide){
      const slide=document.createElement('div');
      slide.className='slide';
      for(let j=0;j<perSlide;j++){
        const idx=i+j;
        if(idx<items.length){
          const card=document.createElement('div');
          card.className='program-card';
          card.innerHTML=`<img src="${items[idx].img}" alt="${items[idx].title}" onerror="this.src='assets/placeholder.jpg'"><h3>${items[idx].title}</h3>`;
          slide.appendChild(card);
        }
      }
      track.appendChild(slide);
      slides.push(slide);
    }
    if(slides.length>0) track.appendChild(slides[0].cloneNode(true));
    return slides.length;
  }

  let itemsPerSlide = (window.innerWidth<768)?1:2;
  let totalSlides = buildCarousel(programTrack, programs, itemsPerSlide);
  let currentIndex=0;
  setInterval(()=> {
    currentIndex++;
    programTrack.style.transform=`translateX(-${currentIndex*100}%)`;
    if(currentIndex>=totalSlides){ 
      programTrack.addEventListener('transitionend',()=>{
        programTrack.style.transition='none';
        currentIndex=0;
        programTrack.style.transform='translateX(0%)';
        setTimeout(()=>programTrack.style.transition='transform .6s ease',50);
      },{once:true});
    }
  },3500);

  window.addEventListener('resize',()=>{
    let newItemsPerSlide=(window.innerWidth<768)?1:2;
    if(newItemsPerSlide!==itemsPerSlide){
      itemsPerSlide=newItemsPerSlide;
      totalSlides=buildCarousel(programTrack, programs, itemsPerSlide);
      currentIndex=0;
      programTrack.style.transition='none';
      programTrack.style.transform='translateX(0%)';
      setTimeout(()=>programTrack.style.transition='transform .6s ease',50);
    }
  });

const collaborators = [
  { name: 'Ministry of Justice', logo: 'assets/logos/justice.jpg' },
  { name: 'National Bank', logo: 'assets/logos/bank.jpeg' },
  { name: 'Notary Offices', logo: 'assets/logos/notary.jpg' },
  { name: 'Law Firms', logo: 'assets/logos/law.jpg' }
];

  const collabGrid=document.getElementById('collabCarousel');
  collaborators.forEach(c=>{
    const div=document.createElement('div');
    div.className='collab';
    div.innerHTML=`<img src="${c.logo}" alt="${c.name}"><p>${c.name}</p>`;
    collabGrid.appendChild(div);
  });

  // Testimonials
  const testimonials=[
    {text:'"This system made inheritance clear and fair."', author:'John D.', photo:'assets/image/john1.jpeg'},
    {text:'"A game-changer for estate planning."', author:'Mary P.', photo:'assets/image/mary.jpeg'},
    {text:'"Safe, reliable, and transparent."', author:'Government Agency', photo:'assets/image/rdb1.png'}
  ];
  const testGrid=document.getElementById('testimonialCarousel');
  testimonials.forEach(t=>{
    const div=document.createElement('div');
    div.className='testimonial';
    div.innerHTML=`<img src="${t.photo}" alt="${t.author}"><p>${t.text}</p><span>– ${t.author}</span>`;
    testGrid.appendChild(div);
  });

})();
</script>
</body>
</html>

