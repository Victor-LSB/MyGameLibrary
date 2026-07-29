(function () {
  const currentUrl = new URL(window.location.href);
  const period = currentUrl.searchParams.get('period') || 'week';
  const tabButtons = Array.from(document.querySelectorAll('.dashboard-tab'));
  const periodLabel = document.getElementById('dashboardPeriodLabel');
  const periodBadge = document.getElementById('dashboardPeriodBadge');
  const totalCompletedCard = document.getElementById('dashboardTotalCompleted');
  const avgTimeCard = document.getElementById('dashboardAvgTime');
  const genreList = document.getElementById('dashboardGenreList');

  const chartPalette = {
    background: '#09090b', // zinc-950
    card: '#18181b', // zinc-900
    violet: '#a78bfa', // violet-400
    violetSoft: 'rgba(167, 139, 250, 0.24)',
    text: '#d4d4d8', // zinc-300
    amber: '#fbbf24', // amber-400 (mesmo tom usado no card de tempo médio)
    amberSoft: 'rgba(251, 191, 36, 0.16)',
    line: '#52525b', // zinc-600
  };

  const genreCanvas = document.getElementById('genreChart');
  const trendCanvas = document.getElementById('trendChart');
  const timeCanvas = document.getElementById('timeChart');
  let genreChart = null;
  let trendChart = null;
  let timeChart = null;
  let dashboardPayload = null;
  let activePeriod = period;

  const periodMap = {
    week: 'semanal',
    month: 'mensal',
    year: 'anual',
  };

  async function loadDashboardData() {
    const response = await fetch(`index.php?action=dashboard_data&period=${encodeURIComponent(period)}`, {
      headers: {
        'Accept': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error('Failed to load dashboard data');
    }

    return response.json();
  }

  function buildBarChart(labels, values) {
    if (!genreCanvas || typeof Chart === 'undefined') return null;

    if (genreChart) {
      genreChart.destroy();
    }

    genreChart = new Chart(genreCanvas, {
      type: 'bar',
      data: {
        labels: labels.map(label => {
          return label.length > 15 ? label.substring(0, 15) + '...' : label;
        }),
        datasets: [
          {
            label: 'Jogos concluídos',
            data: values,
            backgroundColor: [
              chartPalette.violetSoft,
              'rgba(167, 139, 250, 0.55)',
              'rgba(139, 92, 246, 0.4)',
              'rgba(196, 181, 253, 0.35)',
              'rgba(212, 212, 216, 0.2)',
            ],
            borderColor: chartPalette.violet,
            borderWidth: 1,
            borderRadius: 8,
            barThickness: 28,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: chartPalette.text },
          },
          tooltip: {
            callbacks: {
              title(context) {
                return context[0]?.label || '';
              },
              label(context) {
                const value = context.raw ?? 0;
                return `Concluídos: ${Math.round(value)}`;
              }
            }
          }
        },
        scales: {
          x: {
            ticks: { color: chartPalette.text },
            grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: chartPalette.text,
              callback(value) {
                return Math.round(value);
              },
            },
            grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
        },
      },
    });
  }

  function buildLineChart(labels, values) {
    if (!trendCanvas || typeof Chart === 'undefined') return null;

    if (trendChart) {
      trendChart.destroy();
    }

    const points = labels.map((label, index) => ({
      x: index,
      y: Number(values[index] ?? 0),
    }));

    trendChart = new Chart(trendCanvas, {
      type: 'line',
      data: {
        datasets: [
          {
            label: `Tendência de conclusão (${periodMap[period] || 'semanal'})`,
            data: points,
            borderColor: chartPalette.violet,
            backgroundColor: chartPalette.violetSoft,
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 7,
            pointBackgroundColor: chartPalette.violet,
            pointBorderColor: chartPalette.background,
            pointBorderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: chartPalette.text },
          },
        },
        scales: {
          x: {
              type: 'linear',
              min: 0,
              max: Math.max(points.length - 1, 0),
              ticks: {
                  color: chartPalette.text,
                  maxRotation: activePeriod === 'year' ? 45 : 0,
                  minRotation: activePeriod === 'year' ? 45 : 0,
                  callback(value) {
                      return labels[Math.round(Number(value))] ?? '';
                  },
              },
              grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: chartPalette.text,
              callback(value) {
                return Math.round(value);
              },
            },
            grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
        },
      },
    });
  }

  function buildTimeChart(labels, values) {
    if (!timeCanvas || typeof Chart === 'undefined') return null;

    if (timeChart) {
      timeChart.destroy();
    }

    const points = labels.map((label, index) => ({
      x: index,
      y: Number(values[index] ?? 0),
    }));

    timeChart = new Chart(timeCanvas, {
      type: 'line',
      data: {
        datasets: [
          {
            label: 'Tempo médio gasto (horas)',
            data: points,
            borderColor: chartPalette.amber,
            backgroundColor: chartPalette.amberSoft,
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 7,
            pointBackgroundColor: chartPalette.amber,
            pointBorderColor: chartPalette.background,
            pointBorderWidth: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: chartPalette.text },
          },
        },
        scales: {
          x: {
            type: 'linear',
            min: 0,
            max: Math.max(points.length - 1, 0),
            ticks: {
              color: chartPalette.text,
              callback(value) {
                return labels[Math.round(Number(value))] ?? '';
              },
            },
            grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: chartPalette.text,
              callback(value) {
                return Number(value).toFixed(2) + 'h';
              },
            },
            grid: { color: 'rgba(212, 212, 216, 0.08)' },
          },
        },
      },
    });
  }

  function formatAverage(value) {
    return `${Number(value || 0).toFixed(2).replace('.', ',')}h`;
  }

  function toArray(value) {
    if (Array.isArray(value)) {
      return value;
    }

    if (value && typeof value === 'object' && value.constructor === Object) {
      return Object.values(value);
    }

    return [];
  }

  function normalizeSeries(labelsInput, valuesInput) {
    const labels = toArray(labelsInput).map((item) => String(item ?? ''));
    const values = toArray(valuesInput).map((item) => Number(item ?? 0));
    const length = Math.min(labels.length, values.length);

    return {
      labels: labels.slice(0, length),
      values: values.slice(0, length),
    };
  }

  function renderGenreList(genres) {
    if (!genreList) return;

    if (!genres || genres.length === 0) {
      genreList.innerHTML = '<p class="text-zinc-500 italic">Nenhum jogo concluído encontrado para este período.</p>';
      return;
    }

    genreList.innerHTML = `
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        ${genres
          .map(
            (genre) => `
          <div class="bg-zinc-950 border border-zinc-800 rounded-sm p-4 flex items-center justify-between gap-4">
            <span class="font-semibold text-zinc-200" title="${escapeHtml(genre.genre)}">
                ${escapeHtml(genre.genre.length > 25 ? genre.genre.substring(0, 25) + '...' : genre.genre)}
            </span>
            <span class="text-sm font-black text-violet-400">${Number(genre.total) || 0}</span>
          </div>
        `
          )
          .join('')}
      </div>
    `;
  }

  function escapeHtml(text) {
    return String(text)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function setActiveTab(nextPeriod) {
    tabButtons.forEach((button) => {
      const isActive = button.dataset.period === nextPeriod;
      button.classList.toggle('bg-violet-600', isActive);
      button.classList.toggle('text-white', isActive);
      button.classList.toggle('text-zinc-400', !isActive);
      button.classList.toggle('hover:text-white', !isActive);
      button.classList.toggle('hover:bg-zinc-800', !isActive);
    });
  }

  function renderPeriod(nextPeriod) {
    const periodData = dashboardPayload?.periods?.[nextPeriod];
    if (!periodData) return;

    const trendSeries = normalizeSeries(periodData.trend?.labels, periodData.trend?.values);
    const timeTrendSeries = normalizeSeries(periodData.time_trend?.labels, periodData.time_trend?.values);

    activePeriod = nextPeriod;
    const trendContainer = document.getElementById('trendContainer');
    if (trendContainer) {
        if (nextPeriod === 'year') {
            trendContainer.className = 'h-[500px] md:h-[600px]';
        } else {
            trendContainer.className = 'h-[400px] md:h-[500px]';
        }
    }
    setActiveTab(nextPeriod);

    if (periodLabel) periodLabel.textContent = periodData.period_label;
    if (periodBadge) periodBadge.textContent = periodData.period_label;
    if (totalCompletedCard) totalCompletedCard.textContent = String(periodData.summary.total_completed || 0);
    if (avgTimeCard) avgTimeCard.textContent = formatAverage(periodData.summary.avg_time_spent);

    renderGenreList(periodData.genres || []);
    buildBarChart(
      (periodData.genres || []).map((item) => item.genre),
      (periodData.genres || []).map((item) => Number(item.total) || 0)
    );
    buildLineChart(trendSeries.labels, trendSeries.values);
    buildTimeChart(timeTrendSeries.labels, timeTrendSeries.values);
  }

  async function init() {
    try {
      dashboardPayload = await loadDashboardData();

      tabButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          renderPeriod(button.dataset.period || 'week');
        });
      });

      renderPeriod(dashboardPayload.default_period || activePeriod);
    } catch (error) {
      console.error(error);
      if (genreCanvas) {
        genreCanvas.parentElement.insertAdjacentHTML(
          'beforeend',
          '<p class="mt-3 text-sm text-red-300">Não foi possível carregar os dados do gráfico.</p>'
        );
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();