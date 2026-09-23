 

   function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('show');
    }

    function toggleTheme() {
      const html = document.documentElement;
      const current = html.getAttribute('data-bs-theme');
      const icon = document.getElementById('theme-icon');

      if (current === 'light') {
        html.setAttribute('data-bs-theme', 'dark');
        icon.classList.replace('bi-moon-fill', 'bi-sun-fill');
      } else {
        html.setAttribute('data-bs-theme', 'light');
        icon.classList.replace('bi-sun-fill', 'bi-moon-fill');
      }
    }


    const genderCtx = document.getElementById('genderChart').getContext('2d');
    genderCtx.canvas.width = 200;
    genderCtx.canvas.height = 200;
  new Chart(genderCtx, {
    type: 'doughnut',
    data: {
      labels: ['Male', 'Female'],
      datasets: [{
        data: [700, 500],
        backgroundColor: ['#0d6efd', '#dc3545']
      }]
    },
    options : {
        responsive: true,
        maintainAspectRatio: false,
    }
  });

  const loginCtx = document.getElementById('loginChart').getContext('2d');
  loginCtx.canvas.width = 200;
  loginCtx.canvas.height = 92;

  new Chart(loginCtx, {
    type: 'line',
    data: {
      labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
      datasets: [{
        label: 'Logins',
        data: [120, 150, 180, 130, 220],
        borderColor: '#198754',
        backgroundColor: 'rgba(25,135,84,0.2)',
        tension: 0.3
      }]
    }
  });

  /*
  // Gender Distribution
  new Chart(document.getElementById('genderChart'), {
    type: 'pie',
    data: {
      labels: ['Male', 'Female', 'Other'],
      datasets: [{
        data: [500, 450, 74],
        backgroundColor: ['#0d6efd', '#dc3545', '#6f42c1'],
        hoverOffset: 10
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });

  // Login Activity
  new Chart(document.getElementById('loginChart'), {
    type: 'bar',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        label: 'Logins',
        data: [80, 120, 150, 100, 180, 60, 90],
        backgroundColor: '#20c997'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false }
      }
    }
  });

  // Student Performance
  new Chart(document.getElementById('performanceChart'), {
    type: 'radar',
    data: {
      labels: ['Math', 'Science', 'English', 'ICT', 'Art', 'PE'],
      datasets: [{
        label: 'Average Scores (%)',
        data: [75, 82, 78, 88, 70, 60],
        fill: true,
        backgroundColor: 'rgba(13, 110, 253, 0.2)',
        borderColor: '#0d6efd',
        pointBackgroundColor: '#0d6efd'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' }
      }
    }
  });
*/