<template>
  <div class="main-content">
    <breadcumb :page="$t('Checklist_Completion_Report')" :folder="$t('Reports')" />

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
              @input="getReport"
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
              @input="getReport"
            />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('From')">
            <b-form-input v-model="filters.from" type="date" @change="getReport" />
          </b-form-group>
        </b-col>
        <b-col md="2">
          <b-form-group :label="$t('To')">
            <b-form-input v-model="filters.to" type="date" @change="getReport" />
          </b-form-group>
        </b-col>
      </b-row>

      <vue-good-table
        :columns="columns"
        :rows="rows"
        :pagination-options="{ enabled: false }"
        styleClass="tableOne vgt-table"
      />
    </b-card>
  </div>
</template>

<script>
export default {
  name: 'ChecklistCompletionReport',
  data() {
    return {
      isLoading: true,
      rows: [],
      customers: [],
      technicians: [],
      filters: {
        client_id: null,
        technician_id: null,
        from: '',
        to: ''
      },
      columns: [
        { label: this.$t('Category'), field: 'category_name' },
        { label: this.$t('Total_Items'), field: 'total_items' },
        { label: this.$t('Completed_Items'), field: 'completed_items' }
      ]
    };
  },
  async mounted() {
    await this.getReport();
    this.isLoading = false;
  },
  methods: {
    async getReport() {
      const params = {
        client_id: this.filters.client_id,
        technician_id: this.filters.technician_id,
        from: this.filters.from,
        to: this.filters.to
      };
      const { data } = await axios.get('report/service_checklist_completion', {
        params
      });
      this.rows = data.rows || [];
      this.customers = (data.clients || []).map(c => ({ id: c.id, name: c.name }));
      this.technicians = (data.technicians || []).map(t => ({
        id: t.id,
        full_name: t.name || `#${t.id}`
      }));
    }
  }
};
</script>


