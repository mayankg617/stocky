<template>
  <div class="main-content">
    <breadcumb :page="$t('Service_Jobs_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-else>
      <b-row class="mb-3">
        <b-col md="4">
          <b-form-group :label="$t('Customer')">
            <v-select
              :reduce="c => c.id"
              v-model="filters.client_id"
              :options="customers"
              label="name"
              :placeholder="$t('Choose_Customer')"
              @input="getReport(1)"
            />
          </b-form-group>
        </b-col>
        <b-col md="4">
          <b-form-group :label="$t('Technician')">
            <v-select
              :reduce="t => t.id"
              v-model="filters.technician_id"
              :options="technicians"
              label="full_name"
              :placeholder="$t('Choose_Technician')"
              @input="getReport(1)"
            />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('From')">
            <b-form-input v-model="filters.from" type="date" @change="getReport(1)" />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('To')">
            <b-form-input v-model="filters.to" type="date" @change="getReport(1)" />
          </b-form-group>
        </b-col>
      </b-row>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        :pagination-options="{ enabled: true, mode: 'records' }"
        styleClass="tableOne vgt-table"
      />
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'ServiceJobsReport',
  data() {
    return {
      isLoading: true,
      rows: [],
      totalRows: 0,
      customers: [],
      technicians: [],
      serverParams: {
        page: 1,
        perPage: 10
      },
      filters: {
        client_id: null,
        technician_id: null,
        from: '',
        to: ''
      },
      columns: [
        { label: this.$t('date'), field: 'scheduled_date' },
        { label: this.$t('Customer'), field: 'client_name' },
        { label: this.$t('Technician'), field: 'technician_name' },
        { label: this.$t('Service_Item'), field: 'service_item' },
        { label: this.$t('Job_Type'), field: 'job_type' },
        { label: this.$t('Status'), field: 'status' }
      ]
    };
  },
  async mounted() {
    await this.getReport(1);
    this.isLoading = false;
  },
  methods: {
    async getReport(page) {
      if (page) this.serverParams.page = page;
      const params = {
        page: this.serverParams.page,
        limit: this.serverParams.perPage,
        client_id: this.filters.client_id,
        technician_id: this.filters.technician_id,
        from: this.filters.from,
        to: this.filters.to
      };
      const { data } = await axios.get('report/service_jobs', { params });
      this.rows = data.rows || [];
      this.totalRows = data.totalRows || 0;
      this.customers = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
      this.technicians = (data.technicians || []).map(t => ({
        id: t.id,
        full_name: t.name || `#${t.id}`
      }));
    },
    onPageChange({ currentPage }) {
      this.serverParams.page = currentPage;
      this.getReport();
    },
    onPerPageChange({ currentPerPage }) {
      this.serverParams.perPage = currentPerPage;
      this.getReport();
    }
  }
};
</script>


