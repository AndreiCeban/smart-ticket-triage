<!--
TODO: Dashboard Component
Requirements from specification:
- /dashboard route - card counters (tickets per status & per category)
- At least one simple chart (pie or bar) – vanilla canvas or Chart.js
- Dark/light theme toggle
-->

<template>
  <div class="dashboard">
    <div class="dashboard__header">
      <h1 class="dashboard__title">Dashboard</h1>
      <button @click="refreshStats" :disabled="loading" class="btn btn--secondary">
        <span v-if="loading" class="spinner spinner--small"></span>
        <span v-else>
          <ArrowPathIcon class="btn__icon" />
          Refresh
        </span>
      </button>
    </div>

    <div v-if="loading" class="dashboard__loading">
      <div class="spinner"></div>
      <p>Loading dashboard...</p>
    </div>

    <div v-else-if="stats" class="dashboard__content">
      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card__header">
            <h3 class="stat-card__title">Total Tickets</h3>
            <TicketIcon class="stat-card__icon" />
          </div>
          <div class="stat-card__value">{{ stats.total_tickets }}</div>
        </div>

        <div class="stat-card">
          <div class="stat-card__header">
            <h3 class="stat-card__title">Classified Tickets</h3>
            <TagIcon class="stat-card__icon" />
          </div>
          <div class="stat-card__value">{{ stats.classified_tickets }}</div>
          <div class="stat-card__subtitle">
            {{ classificationPercentage }}% classified
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-card__header">
            <h3 class="stat-card__title">Average Confidence</h3>
            <ChartBarIcon class="stat-card__icon" />
          </div>
          <div class="stat-card__value">{{ averageConfidencePercentage }}%</div>
          <div class="stat-card__subtitle">AI confidence score</div>
        </div>

        <div class="stat-card">
          <div class="stat-card__header">
            <h3 class="stat-card__title">Open Tickets</h3>
            <ClipboardDocumentListIcon class="stat-card__icon" />
          </div>
          <div class="stat-card__value">{{ stats.by_status.open || 0 }}</div>
          <div class="stat-card__subtitle">Need attention</div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="charts-section">
        <div class="chart-container">
          <h3 class="chart-container__title">Tickets by Status</h3>
          <canvas ref="statusChart" class="chart-container__canvas"></canvas>
        </div>

        <div class="chart-container">
          <h3 class="chart-container__title">Tickets by Category</h3>
          <canvas ref="categoryChart" class="chart-container__canvas"></canvas>
        </div>
      </div>

      <!-- Detailed Stats Tables -->
      <div class="details-section">
        <div class="details-table">
          <h3 class="details-table__title">Status Breakdown</h3>
          <table class="details-table__table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(count, status) in stats.by_status" :key="status">
                <td>
                  <span class="status-badge" :class="`status-badge--${status}`">
                    {{ getStatusLabel(status) }}
                  </span>
                </td>
                <td>{{ count }}</td>
                <td>{{ Math.round((count / stats.total_tickets) * 100) }}%</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="details-table">
          <h3 class="details-table__title">Category Breakdown</h3>
          <table class="details-table__table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Count</th>
                <th>Percentage</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(count, category) in stats.by_category" :key="category">
                <td>
                  <span class="category-badge">{{ getCategoryLabel(category) }}</span>
                </td>
                <td>{{ count }}</td>
                <td>{{ Math.round((count / stats.classified_tickets) * 100) }}%</td>
              </tr>
              <tr v-if="unclassifiedCount > 0">
                <td>
                  <span class="category-badge category-badge--empty">Unclassified</span>
                </td>
                <td>{{ unclassifiedCount }}</td>
                <td>{{ Math.round((unclassifiedCount / stats.total_tickets) * 100) }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-else class="dashboard__error">
      <h2>Failed to Load Dashboard</h2>
      <p>Unable to fetch dashboard statistics.</p>
      <button @click="refreshStats" class="btn btn--primary">
        <ArrowPathIcon class="btn__icon" />
        Try Again
      </button>
    </div>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
import { 
  ArrowPathIcon, 
  TicketIcon, 
  TagIcon, 
  ChartBarIcon, 
  ClipboardDocumentListIcon 
} from '@heroicons/vue/24/outline'

Chart.register(...registerables);

export default {
  name: 'Dashboard',
  components: {
    ArrowPathIcon,
    TicketIcon,
    TagIcon,
    ChartBarIcon,
    ClipboardDocumentListIcon
  },
  data() {
    return {
      stats: null,
      loading: true,
      statusChart: null,
      categoryChart: null
    }
  },
  computed: {
    classificationPercentage() {
      if (!this.stats || this.stats.total_tickets === 0) return 0;
      return Math.round((this.stats.classified_tickets / this.stats.total_tickets) * 100);
    },
    averageConfidencePercentage() {
      if (!this.stats || !this.stats.average_confidence) return 0;
      return Math.round(this.stats.average_confidence * 100);
    },
    unclassifiedCount() {
      if (!this.stats) return 0;
      return this.stats.total_tickets - this.stats.classified_tickets;
    }
  },
  async mounted() {
    await this.fetchStats();
  },
  beforeUnmount() {
    // Clean up charts
    if (this.statusChart) {
      this.statusChart.destroy();
    }
    if (this.categoryChart) {
      this.categoryChart.destroy();
    }
  },
  methods: {
    async fetchStats() {
      try {
        this.loading = true;
        const response = await fetch('/api/stats');
        if (!response.ok) throw new Error('Failed to fetch stats');
        
        const data = await response.json();
        console.log('Dashboard stats data:', data);
        this.stats = data;
        
        // Create charts after stats are loaded
        this.$nextTick(() => {
          // Add a small delay to ensure canvas elements are fully rendered
          setTimeout(() => {
            this.createCharts();
          }, 100);
        });
        
      } catch (error) {
        console.error('Error fetching stats:', error);
        this.stats = null;
      } finally {
        this.loading = false;
      }
    },
    
    async refreshStats() {
      await this.fetchStats();
    },
    
    createCharts() {
      console.log('Creating charts...');
      this.createStatusChart();
      this.createCategoryChart();
    },
    
    createStatusChart() {
      try {
        if (this.statusChart) {
          this.statusChart.destroy();
        }
        
        if (!this.$refs.statusChart) {
          console.error('Status chart canvas not found');
          return;
        }
        
        const ctx = this.$refs.statusChart.getContext('2d');
        const statusData = this.stats.by_status;
        
        if (!statusData || Object.keys(statusData).length === 0) {
          console.error('No status data available');
          return;
        }
        
        const labels = Object.keys(statusData).map(status => this.getStatusLabel(status));
        const data = Object.values(statusData);
        const colors = this.getStatusColors();
        
        this.statusChart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: data,
              backgroundColor: colors,
              borderWidth: 2,
              borderColor: '#ffffff'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  padding: 20,
                  usePointStyle: true
                }
              }
            }
          }
        });
      } catch (error) {
        console.error('Error creating status chart:', error);
      }
    },
    
    createCategoryChart() {
      try {
        if (this.categoryChart) {
          this.categoryChart.destroy();
        }
        
        if (!this.$refs.categoryChart) {
          console.error('Category chart canvas not found');
          return;
        }
        
        const ctx = this.$refs.categoryChart.getContext('2d');
        const categoryData = { ...this.stats.by_category };
        
        // Add unclassified if there are any
        if (this.unclassifiedCount > 0) {
          categoryData.unclassified = this.unclassifiedCount;
        }
        
        if (!categoryData || Object.keys(categoryData).length === 0) {
          console.error('No category data available');
          return;
        }
        
        const labels = Object.keys(categoryData).map(category => 
          category === 'unclassified' ? 'Unclassified' : this.getCategoryLabel(category)
        );
        const data = Object.values(categoryData);
        const colors = this.getCategoryColors(Object.keys(categoryData));
        
        this.categoryChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: labels,
            datasets: [{
              label: 'Tickets',
              data: data,
              backgroundColor: colors,
              borderWidth: 1,
              borderColor: '#ffffff'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  stepSize: 1
                }
              }
            }
          }
        });
      } catch (error) {
        console.error('Error creating category chart:', error);
      }
    },
    
    getStatusColors() {
      return [
        '#3b82f6', // blue - open
        '#f59e0b', // amber - in_progress
        '#10b981', // green - resolved
        '#6b7280'  // gray - closed
      ];
    },
    
    getCategoryColors(categories) {
      const colorMap = {
        technical: '#ef4444',    // red
        billing: '#f59e0b',      // amber
        account: '#3b82f6',      // blue
        feature_request: '#8b5cf6', // purple
        bug_report: '#f97316',   // orange
        general: '#10b981',      // green
        unclassified: '#6b7280'  // gray
      };
      
      return categories.map(category => colorMap[category] || '#6b7280');
    },
    
    getStatusLabel(status) {
      const labels = {
        open: 'Open',
        in_progress: 'In Progress',
        resolved: 'Resolved',
        closed: 'Closed'
      };
      return labels[status] || status;
    },
    
    getCategoryLabel(category) {
      const labels = {
        technical: 'Technical Support',
        billing: 'Billing & Payment',
        account: 'Account Management',
        feature_request: 'Feature Request',
        bug_report: 'Bug Report',
        general: 'General Inquiry'
      };
      return labels[category] || category;
    }
  }
}
</script>
