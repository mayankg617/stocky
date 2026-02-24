<template>
  <div class="main-content">
    <breadcumb :page="$t('Report_Transactions')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

      <b-col md="12" class="text-center" v-if="!isLoading">
        <date-range-picker 
          v-model="dateRange" 
          :startDate="startDate" 
          :endDate="endDate" 
           @update="Submit_filter_dateRange"
          :locale-data="locale" > 

          <template v-slot:input="picker" style="min-width: 350px;">
              {{ picker.startDate.toJSON().slice(0, 10)}} - {{ picker.endDate.toJSON().slice(0, 10)}}
          </template>        
        </date-range-picker>
      </b-col>

    <b-card class="wrapper" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :group-options="{
          enabled: true,
          headerPosition: 'bottom',
        }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="table-hover tableOne vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'date'">
            {{ formatDisplayDate(props.row.date) }}
          </span>
          <span v-else-if="props.column.field === 'Ref_Sale' && props.row.type === 'sale' && props.row.sale_id">
            <router-link :to="{ name: 'detail_sale', params: { id: props.row.sale_id } }" class="text-primary">
              {{ props.formattedRow[props.column.field] }}
            </router-link>
          </span>
          <span v-else-if="props.column.field === 'Ref_Sale' && props.row.type === 'purchase' && props.row.purchase_id">
            <router-link :to="{ name: 'detail_purchase', params: { id: props.row.purchase_id } }" class="text-primary">
              {{ props.formattedRow[props.column.field] }}
            </router-link>
          </span>
          <span v-else-if="props.column.field == 'montant'">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, props.row.montant, 2) }}
          </span>
          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
          <b-button @click="Payment_PDF()" size="sm" variant="outline-success ripple m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="payments"
              :columns="columns"
              :file-name="'payments'"
              :file-type="'xlsx'"
              :sheet-name="'payments'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
        </div>
      </vue-good-table>

       <!-- 🔽 Payment Summary Table Below -->
      <b-card class="mt-4" header="Summary by Payment Method">
          <!-- PDF Button -->
          <div class="mb-3 text-right">
            <b-button @click="Payment_Summary_PDF()" size="sm" variant="outline-primary ripple">
              <i class="i-File-Copy"></i> Summary PDF
            </b-button>
          </div>

        <b-table striped hover small :items="payment_summary" :fields="[
          { key: 'payment_method', label: 'Payment Method' },
          { key: 'sale_total', label: 'Total Sales' },
          { key: 'purchase_total', label: 'Total Purchases' },
          { key: 'expense_total', label: 'Total Expenses' }
        ]" responsive>
          <template #cell(sale_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.sale_total, 2) }}
          </template>
          <template #cell(purchase_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.purchase_total, 2) }}
          </template>
          <template #cell(expense_total)="data">
            {{ formatPriceWithSymbol(currentUser && currentUser.currency, data.item.expense_total, 2) }}
          </template>
        </b-table>
      </b-card>
    </b-card>



    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
         
          <!-- Customers  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_client"
                :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Sale  -->
          <b-col md="12">
            <b-form-group :label="$t('Sale')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_sale"
                :options="sales.map(sales => ({label: sales.Ref, value: sales.id}))"
              />
            </b-form-group>
          </b-col>


           <!-- Supplier  -->
           <b-col md="12">
            <b-form-group :label="$t('Supplier')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Supplier')"
                v-model="Filter_provider"
                :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
              />
            </b-form-group>
          </b-col>

       
           <!-- Purchase  -->
           <b-col md="12">
            <b-form-group :label="$t('Purchase')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                v-model="Filter_purchase"
                :options="purchases.map(purchases => ({label: purchases.Ref, value: purchases.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Payment choice -->
          <b-col md="12">
            <b-form-group :label="$t('Paymentchoice')">
              <v-select
                v-model="Filter_Reg"
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                :options="payment_methods.map(payment_methods => ({label: payment_methods.name, value: payment_methods.id}))"

              ></v-select>
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Payments_Sales(serverParams.page)"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>
  </div>
</template>


<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import Util from '../../../../utils'
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Report Transactions"
  },
  components: { DateRangePicker },

  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: "",
      Filter_client: "",
      Filter_sale: "",
      Filter_provider: "",
      Filter_purchase: "",
      Filter_Reg: "",
      payments: [],
      payment_methods:[],
      clients: [],
      suppliers: [],
      payment_summary: [],
      rows: [{
        payment_method: 'Total',
         
          children: [
             
          ],
      },],
      sales: [],
      purchases: [],
      today_mode: true,
      startDate: "", 
      endDate: "", 
      dateRange: { 
       startDate: "", 
       endDate: "" 
      }, 
      locale:{ 
          //separator between the two ranges apply
          Label: "Apply", 
          cancelLabel: "Cancel", 
          weekLabel: "W", 
          customRangeLabel: "Custom Range", 
          daysOfWeek: moment.weekdaysMin(), 
          //array of days - see moment documenations for details 
          monthNames: moment.monthsShort(), //array of month names - see moment documenations for details 
          firstDay: 1 //ISO first day of week - see moment documenations for details
        },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null
    };
  },

  computed: {
    columns() {
      return [
        {
          label: this.$t("Date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Sale_Purchase_Ref"),
          field: "Ref_Sale",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer_Provider"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Payment_Method"),
          field: "payment_method",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Account"),
          field: "account_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Amount"),
          field: "montant",
          // Let headerField return a formatted string; avoid vue-good-table's decimal re-formatting.
          headerField: this.sumCount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("AddedBy"),
          field: "user_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }

  },
  methods: {

    // Group footer helper for vue-good-table.
    // Returns a formatted string so the footer row inside the table
    // looks like a normal data row, but uses the global price format & currency.
    sumCount(rowObj) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, 0, 2);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = Number(rowObj.children[i].montant) || 0;
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPriceWithSymbol(this.currentUser && this.currentUser.currency, sum, 2);
    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Payments_Sales(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Payments_Sales(1);
      }
    },

    //---- Event on Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "Ref_Sale") {
        field = "sale_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Payments_Sales(this.serverParams.page);
    },
    //----------------------------------------- Format Display Date (for tables) -------------------------------\\
    formatDisplayDate(value) {
      if (!value) return '';
      // Get date format from Vuex store (loaded from database) or fallback
      const dateFormat = this.$store.getters.getDateFormat || Util.getDateFormat(this.$store);
      return Util.formatDisplayDate(value, dateFormat);
    },

    // Price formatting for display only (does NOT affect calculations or stored values)
    // Uses the global/system price_format setting when available; otherwise falls back
    // to the existing behavior to preserve current behavior.
    formatPriceDisplay(number, dec) {
      try {
        const n = Number(number || 0);
        if (isNaN(n)) {
          const n2 = Number(number || 0);
          return n2.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
        }
        
        const decimals = Number.isInteger(dec) ? dec : (dec ? parseInt(dec) : 2);
        // Always check store directly to ensure we get the latest value
        const key = getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },

    formatPriceWithSymbol(symbol, number, dec) {
      try {
        const safeSymbol = symbol || (this.currentUser && this.currentUser.currency) || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      } catch (e) {
        const safeSymbol = symbol || "";
        const value = this.formatPriceDisplay(number, dec);
        return safeSymbol ? `${safeSymbol} ${value}` : value;
      }
    },

    //---- Event on Search

    onSearch(value) {
      this.search = value.searchTerm;
      this.Payments_Sales(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_client = "";
      this.Filter_sale = "";
      this.Filter_provider = "";
      this.Filter_purchase = "";
      this.Filter_Reg = "";
      this.Payments_Sales(this.serverParams.page);
    },

    //---------------------------------------- Set To Strings-------------------------\\
    setToStrings() {
      // Simply replaces null values with strings=''
      if (this.Filter_client === null) {
        this.Filter_client = "";
      } else if (this.Filter_sale === null) {
        this.Filter_sale = "";
      } else if (this.Filter_purchase === null) {
        this.Filter_purchase = "";
      } else if (this.Filter_provider === null) {
        this.Filter_provider = "";
      }
    },

    Payment_PDF() {
      const pdf = new jsPDF("p", "pt");

      // Use custom font
      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold");
      pdf.setFont("VazirmatnBold");

      const columns = [
        { header: "Date", dataKey: "date" },
        { header: "Reference", dataKey: "Ref" },
        { header: "Sale / Purchase Ref", dataKey: "Ref_Sale" },
        { header: "Customer / Provider", dataKey: "client_name" },
        { header: "Payment Method", dataKey: "payment_method" },
        { header: "Account", dataKey: "account_name" },
        { header: "Amount", dataKey: "montant" },
        { header: "Added By", dataKey: "user_name" }
      ];

      // Calculate total amount
      const totalGrandTotal = this.payments.reduce(
        (sum, payment) => sum + parseFloat(payment.montant || 0),
        0
      );

      const footer = [{
        date: "Total",
        Ref: '',
        Ref_Sale: '',
        client_name: '',
        payment_method: '',
        account_name: '',
        montant: `${totalGrandTotal.toFixed(2)}`,
        user_name: ''
      }];

      autoTable(pdf, {
        columns: columns,
        body: this.payments,
        foot: footer,
        startY: 70,
        theme: "grid",
        didDrawPage: (data) => {
          pdf.setFont("VazirmatnBold");
          pdf.setFontSize(18);
          pdf.text("Report Transactions", 40, 25);
        },
        styles: {
          font: "VazirmatnBold",
          halign: "center"
        },
        headStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        },
        footStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        }
      });

      pdf.save("Report_Transactions.pdf");
    },


    Payment_Summary_PDF() {
      const pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      pdf.addFont(fontPath, "VazirmatnBold", "bold");
      pdf.setFont("VazirmatnBold");

      const summaryColumns = [
        { header: "Payment Method", dataKey: "payment_method" },
        { header: "Total Sales", dataKey: "sale_total" },
        { header: "Total Purchases", dataKey: "purchase_total" },
        { header: "Total Expenses", dataKey: "expense_total" }
      ];

      const summaryBody = this.payment_summary.map(item => ({
        payment_method: item.payment_method,
        sale_total: item.sale_total.toFixed(2),
        purchase_total: item.purchase_total.toFixed(2),
        expense_total: item.expense_total.toFixed(2)
      }));

      autoTable(pdf, {
        columns: summaryColumns,
        body: summaryBody,
        startY: 70,
        theme: "grid",
        didDrawPage: () => {
          pdf.setFontSize(18);
          pdf.text("Payment Summary Report", 40, 25);
        },
        styles: {
          font: "VazirmatnBold",
          halign: "center"
        },
        headStyles: {
          fillColor: [26, 86, 219],
          textColor: 255,
          fontStyle: "bold"
        }
      });

      pdf.save("Payment_Summary_Report.pdf");
    },


     //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Payments_Sales(1);
    },


    get_data_loaded() {
      var self = this;
      if (self.today_mode) {
        let today = new Date()

        self.startDate = today.getFullYear();
        self.endDate = new Date().toJSON().slice(0, 10);

        self.dateRange.startDate = today.getFullYear();
        self.dateRange.endDate = new Date().toJSON().slice(0, 10);
        
      }
    },

    //-------------------------------- Get All Payments Sales ---------------------\\
    Payments_Sales(page) {
      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Mark loading
      this.isLoading = true;
      this.get_data_loaded();

      axios
        .get("report/report_transactions", {
          params: {
            page: page,
            client_id: this.Filter_client,
            sale_id: this.Filter_sale,
            provider_id: this.Filter_provider,      // <-- added
            purchase_id: this.Filter_purchase,      // <-- added
            payment_method_id: this.Filter_Reg,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            to: this.endDate,
            from: this.startDate,
          }
        })
        .then(response => {
          this.payments = response.data.payments;
          this.clients = response.data.clients;
          this.suppliers = response.data.suppliers;
          this.sales = response.data.sales;
          this.purchases = response.data.purchases;
          this.payment_methods = response.data.payment_methods;
          this.payment_summary = response.data.payment_summary;
          this.totalRows = response.data.totalRows;

          // if using a tree-table or nested row grouping
          if (this.rows && this.rows[0]) {
            this.rows[0].children = this.payments;
          }

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(error => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    }

  },

  //----------------------------- Created function-------------------\\
  created: function() {
    // Initialize price format key from Vuex store (get_user_auth API)
    try {
      const key = getPriceFormatSetting({ store: this.$store });
      if (key) {
        this.price_format_key = key;
      }
    } catch (e) {
      // ignore
    }
    this.Payments_Sales(1);
  }
};
</script>