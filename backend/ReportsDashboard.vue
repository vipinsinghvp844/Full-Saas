<template>
  <div class="p-6 bg-gray-50 min-h-screen">
    <!-- Header & Filters -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-gray-800">SaaS Performance Overview</h1>
      <div class="flex gap-4">
        <input type="date" v-model="filters.start_date" class="border rounded p-2" />
        <input type="date" v-model="filters.end_date" class="border rounded p-2" />
        <button @click="fetchReports" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">
          Apply Filters
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-20 text-gray-500">Loading insights...</div>

    <div v-else>
      <!-- Summary Cards (Growth Metrics) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
          <h3 class="text-gray-500 text-sm font-semibold uppercase">Total Revenue</h3>
          <div class="flex items-end gap-3 mt-2">
            <span class="text-3xl font-bold">${{ formatCurrency(reports.revenue.total_revenue) }}</span>
            <span :class="reports.growth.revenue_growth_pct >= 0 ? 'text-green-500' : 'text-red-500'" class="text-sm font-medium">
              {{ reports.growth.revenue_growth_pct }}%
            </span>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
          <h3 class="text-gray-500 text-sm font-semibold uppercase">Total Gyms</h3>
          <div class="flex items-end gap-3 mt-2">
            <span class="text-3xl font-bold">{{ reports.gyms.total }}</span>
            <span :class="reports.growth.gym_growth_pct >= 0 ? 'text-green-500' : 'text-red-500'" class="text-sm font-medium">
              {{ reports.growth.gym_growth_pct }}%
            </span>
          </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
          <h3 class="text-gray-500 text-sm font-semibold uppercase">Active Subscriptions</h3>
          <div class="flex items-end gap-3 mt-2">
            <span class="text-3xl font-bold">{{ reports.subscriptions.active }}</span>
            <span class="text-gray-500 text-sm">Churn: {{ reports.gyms.churn_rate_pct }}%</span>
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Monthly Revenue Chart -->
        <div class="bg-white p-6 rounded-lg shadow">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Monthly Revenue</h3>
          <apexchart type="area" height="350" :options="revenueChartOptions" :series="revenueSeries"></apexchart>
        </div>

        <!-- Plan Distribution Pie Chart -->
        <div class="bg-white p-6 rounded-lg shadow">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Subscription Plan Distribution</h3>
          <apexchart type="donut" height="350" :options="planChartOptions" :series="planSeries"></apexchart>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

const reports = ref(null);
const loading = ref(true);
const filters = ref({
  start_date: '',
  end_date: '',
});

const fetchReports = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/super-admin/reports', { params: filters.value });
    reports.value = response.data.data;
  } catch (error) {
    console.error("Failed to load reports", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchReports);

const formatCurrency = (val) => {
  return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 });
};

// --- Chart Configurations ---

const revenueSeries = computed(() => {
  if (!reports.value) return [];
  return [{
    name: 'Revenue',
    data: reports.value.revenue.monthly_chart.map(item => item.total)
  }];
});

const revenueChartOptions = computed(() => {
  if (!reports.value) return {};
  return {
    chart: { type: 'area', toolbar: { show: false } },
    xaxis: {
      categories: reports.value.revenue.monthly_chart.map(item => item.month),
    },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth' },
    colors: ['#3b82f6'],
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] }
    }
  };
});

const planSeries = computed(() => {
  if (!reports.value) return [];
  return reports.value.subscriptions.plan_distribution.map(item => item.count);
});

const planChartOptions = computed(() => {
  if (!reports.value) return {};
  return {
    labels: reports.value.subscriptions.plan_distribution.map(item => item.name),
    chart: { type: 'donut' },
    colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6'],
  };
});
</script>