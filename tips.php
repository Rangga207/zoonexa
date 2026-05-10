<?php
require 'config.php';
requireLogin();

$page_title = 'Health Tips';
include 'header.php';
?>

<section class="page-section">
  <div class="page-header">
    <div>
      <h1>Health Tips</h1>
      <p class="muted">Simple, practical advice to help you build healthier habits — no fluff.</p>
    </div>
  </div>

  <!-- Steps -->
  <div class="card tips-card">
    <div class="tips-header">
      <span class="tips-icon"><i class="fas fa-walking" style="color: var(--primary);"></i></span>
      <h2>Steps & Walking</h2>
    </div>
    <div class="tips-list">
      <div class="tip-item">
        <h4>Start with small goals</h4>
        <p class="muted">If you're not hitting 8,000 steps yet, aim for 5,000 first. Build up gradually over 1–2 weeks before increasing your target.</p>
      </div>
      <div class="tip-item">
        <h4>Take the stairs</h4>
        <p class="muted">Skip the elevator whenever possible. Stairs burn more calories and build leg strength over time.</p>
      </div>
      <div class="tip-item">
        <h4>Walk after meals</h4>
        <p class="muted">A 10–15 minute walk after eating helps with digestion and naturally adds steps to your day without feeling like exercise.</p>
      </div>
      <div class="tip-item">
        <h4>Park farther away</h4>
        <p class="muted">When going to the store or work, park a bit farther from the entrance. Those extra steps add up throughout the day.</p>
      </div>
    </div>
  </div>

  <!-- Sleep -->
  <div class="card tips-card" style="margin-top: 24px;">
    <div class="tips-header">
      <span class="tips-icon"><i class="fas fa-bed" style="color: #3498db;"></i></span>
      <h2>Sleep</h2>
    </div>
    <div class="tips-list">
      <div class="tip-item">
        <h4>Keep a consistent schedule</h4>
        <p class="muted">Go to bed and wake up at the same time every day — even on weekends. Your body clock responds best to routine.</p>
      </div>
      <div class="tip-item">
        <h4>No screens 1 hour before bed</h4>
        <p class="muted">Blue light from phones and laptops tricks your brain into thinking it's daytime. Put devices away before winding down.</p>
      </div>
      <div class="tip-item">
        <h4>Keep your room cool and dark</h4>
        <p class="muted">The ideal sleeping temperature is around 16–19°C (60–66°F). Blackout curtains can make a big difference.</p>
      </div>
      <div class="tip-item">
        <h4>Avoid caffeine after 2 PM</h4>
        <p class="muted">Caffeine has a half-life of about 5–6 hours. An afternoon coffee can still be in your system when it's time to sleep.</p>
      </div>
    </div>
  </div>

  <!-- Hydration -->
  <div class="card tips-card" style="margin-top: 24px;">
    <div class="tips-header">
      <span class="tips-icon"><i class="fas fa-tint" style="color: #00a8ff;"></i></span>
      <h2>Hydration</h2>
    </div>
    <div class="tips-list">
      <div class="tip-item">
        <h4>Start your day with water</h4>
        <p class="muted">Drink a glass of water first thing in the morning before coffee or food. It wakes up your body and kickstarts your metabolism.</p>
      </div>
      <div class="tip-item">
        <h4>Carry a water bottle</h4>
        <p class="muted">Having water within reach makes you drink more without even thinking about it. Aim for at least 2 liters per day.</p>
      </div>
      <div class="tip-item">
        <h4>Eat water-rich foods</h4>
        <p class="muted">Fruits and vegetables like watermelon, cucumbers, and oranges count toward your hydration too — not just plain water.</p>
      </div>
      <div class="tip-item">
        <h4>Set reminders</h4>
        <p class="muted">If you forget to drink, set a reminder on your phone every hour. It takes about 21 days to build the habit.</p>
      </div>
    </div>
  </div>

  <!-- Exercise -->
  <div class="card tips-card" style="margin-top: 24px;">
    <div class="tips-header">
      <span class="tips-icon"><i class="fas fa-dumbbell" style="color: #e74c3c;"></i></span>
      <h2>Exercise</h2>
    </div>
    <div class="tips-list">
      <div class="tip-item">
        <h4>Something is better than nothing</h4>
        <p class="muted">You don't need a gym or a perfect workout plan. Even 10 minutes of stretching or bodyweight exercises counts.</p>
      </div>
      <div class="tip-item">
        <h4>Warm up before, cool down after</h4>
        <p class="muted">Spend 5 minutes warming up with light movement before your workout. This reduces injury risk and makes it feel easier.</p>
      </div>
      <div class="tip-item">
        <h4>Mix it up</h4>
        <p class="muted">Doing the same thing every day gets boring fast. Try different activities — walking, cycling, yoga, or home workouts — to stay motivated.</p>
      </div>
      <div class="tip-item">
        <h4>Rest days matter</h4>
        <p class="muted">Your muscles grow and recover during rest days. Don't skip them. Light stretching or a walk is a great way to stay active while recovering.</p>
      </div>
    </div>
  </div>

  <!-- BMI -->
  <div class="card tips-card" style="margin-top: 24px;">
    <div class="tips-header">
      <span class="tips-icon"><i class="fas fa-ruler" style="color: #f39c12;"></i></span>
      <h2>Understanding BMI</h2>
    </div>
    <div class="tips-list">
      <div class="tip-item">
        <h4>BMI is a rough guide, not the full picture</h4>
        <p class="muted">BMI doesn't account for muscle mass, bone density, or body composition. Use it as one data point among many — not a definitive measure of health.</p>
      </div>
      <div class="tip-item">
        <h4>General BMI ranges</h4>
        <p class="muted">
          <strong>Underweight:</strong> Below 18.5 |
          <strong>Normal:</strong> 18.5 – 24.9 |
          <strong>Overweight:</strong> 25 – 29.9 |
          <strong>Obese:</strong> 30 and above
        </p>
      </div>
      <div class="tip-item">
        <h4>Focus on how you feel</h4>
        <p class="muted">Energy levels, sleep quality, and how your body feels day to day are often better indicators of health than any single number.</p>
      </div>
    </div>
  </div>

</section>

<?php include 'footer.php'; ?>
