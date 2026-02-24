<template>
  <div class="main-content">
    <breadcumb :page="$t('Login_Activity_Report')" :folder="$t('Reports')" />

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper" v-if="!isLoading">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h5 class="mb-1">{{ $t('Login_Activity_Report') }}</h5>
          <p class="text-muted mb-0">
            Historical login activity for your user account (all sessions including inactive ones).
          </p>
        </div>
        <div class="d-flex">
          <b-button
            variant="outline-primary"
            class="mr-2"
            @click="LoadLoginActivity(serverParams.page)"
            :disabled="isLoading"
          >
            Refresh
          </b-button>
        </div>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="sessions"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        :search-options="{
          placeholder: $t('Search_this_table'),
          enabled: false,
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          perPage: serverParams.perPage,
          setCurrentPage: serverParams.page,
          perPageDropdown: [10, 20, 50, 100],
          dropdownAllowAll: true,
          allText: 'All',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'device'">
            <div class="d-flex align-items-center">
              <span>{{ props.row.device }}</span>
              <b-badge v-if="props.row.is_current" variant="success" class="ms-2">Current</b-badge>
              <b-badge v-else-if="props.row.is_active" variant="info" class="ms-2">Active</b-badge>
              <b-badge v-else variant="secondary" class="ms-2">Inactive</b-badge>
            </div>
          </span>
          <span v-else-if="props.column.field == 'ip_address'">
            {{ props.row.ip_address || '-' }}
          </span>
          <span v-else-if="props.column.field == 'login_at'">
            {{ formatDateTime(props.row.login_at) }}
          </span>
          <span v-else-if="props.column.field == 'last_activity_at'">
            {{ props.row.last_activity_at ? formatDateTime(props.row.last_activity_at) : '-' }}
          </span>
          <span v-else-if="props.column.field == 'status'">
            <b-badge v-if="props.row.is_current" variant="success">Current</b-badge>
            <b-badge v-else-if="props.row.revoked_at" variant="danger">Logged Out</b-badge>
            <b-badge v-else-if="props.row.is_active" variant="info">Active</b-badge>
            <b-badge v-else variant="secondary">Expired</b-badge>
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";
import Util from '../../../../utils';

export default {
  metaInfo: {
    title: "Login Activity Report"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "login_at",
          type: "desc"
        },
        page: 1,
        perPage: 50
      },
      limit: "50",
      totalRows: 0,
      sessions: [],
    };
  },
  computed: {
    ...mapGetters(["currentUserPermissions"]),
    columns() {
      return [
        {
          label: "Device / Browser",
          field: "device",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: "IP Address",
          field: "ip_address",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: "Login date & time",
          field: "login_at",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: "Last activity",
          field: "last_activity_at",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: "Status",
          field: "status",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },
  created() {
    // Permission gate (UI). Backend also enforces.
    const perms = this.currentUserPermissions || [];
    const allowed = perms.includes("login_device_management") || perms.includes("setting_system");
    if (!allowed) {
      this.$router.push({ name: "not_authorize" });
      return;
    }
    this.LoadLoginActivity(1);
  },
  methods: {
    formatDateTime(v) {
      try {
        if (!v) return "";
        const d = new Date(v);
        if (isNaN(d.getTime())) return String(v);
        // Get date format from Vuex store (loaded from database) or fallback
        const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
        // formatDisplayDate now preserves time automatically
        return Util.formatDisplayDate(d.toISOString(), dateFormat);
      } catch (e) {
        return String(v || "");
      }
    },
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },
    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.LoadLoginActivity(currentPage);
      }
    },
    //---- Event Per Page Change
    onPerPageChange(params) {
      const perPage = params.currentPerPage === -1 ? -1 : params.currentPerPage;
      this.updateParams({ perPage: perPage, page: 1 });
      this.limit = perPage === -1 ? '-1' : perPage.toString();
      this.LoadLoginActivity(1);
    },
    LoadLoginActivity(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.isLoading = true;
      
      axios
        .get(
          "security/login-activity-report?page=" +
            page +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.sessions = (response && response.data && response.data.sessions) ? response.data.sessions : [];
          this.totalRows = response.data.totalRows || 0;
          // Complete the animation of the progress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(error => {
          // Complete the animation of the progress bar.
          NProgress.done();
          const msg =
            (error && error.response && error.response.data && (error.response.data.message || error.response.data.error)) ||
            this.$t("Failed");
          this.makeToast("danger", msg, this.$t("Failed"));
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  }
};
</script>

