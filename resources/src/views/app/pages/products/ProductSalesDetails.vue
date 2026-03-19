<template>
  <div class="main-content">

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card no-body v-if="!isLoading">
      <b-card-header>
        <button @click="print_product()" class="btn btn-outline-primary">
          <i class="i-Billing"></i>
          {{ $t('print') }}
        </button>
      </b-card-header>
      <b-card-body>
        <b-row id="print_product">
          <b-col md="12" class="mb-5" v-if="product.type != 'is_variant'">
            <h3>{{ product.name }}</h3>
          </b-col>

          <b-col md="12" class="mt-4">

            <div class="sales-card">
              <h3 class="section-title">Sales</h3>

              <table class="sales-table">
                <thead>
                  <tr>
                    <th>DATE</th>
                    <th>FARMER NAME</th>
                    <th>BRAND</th>
                    <th>QUANTITY</th>
                    <th>BATCH NUMBER</th>
                    <th>EXPIRY DATE</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sale in sales" :key="sale.sale_id">
                    <td>{{ sale.date }}</td>
                    <td>{{ sale.client_name }}</td>
                    <td>{{ sale.purchase_name}}</td>
                    <td>{{ sale.quantity }}</td>
                    <td>{{ sale.batch_no }}</td>
                    <td>{{ sale.expiry_date }}</td>
                  </tr>

                </tbody>
              </table>
              </div>
          </b-col>










        </b-row>
        <hr v-show="product.note">

      </b-card-body>
    </b-card>
  </div>
</template>





<script>
export default {
  data() {
    return {
      product: {},
      sales: []
    };
  },

  mounted() {
    this.fetchDetails();
  },

  methods: {
    fetchDetails() {
      axios.get(`/products/${this.$route.params.id}/sales-details`)
        .then(res => {
          this.product = res.data.product;
          this.sales = res.data.sales;
        });
    }
  }
};
</script>
<style scoped>

/* ===== CARD ===== */
.sales-card {
  background: #ffffff;
  padding: 26px;
  border-radius: 14px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}

/* ===== TITLE ===== */
.section-title {
  font-size: 22px;
  font-weight: 600;
  color: #1f2d3d;
  margin-bottom: 18px;
}

/* ===== TABLE RESET ===== */
.sales-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 15px;
}

/* HEADER */
.sales-table thead tr {
  background: #f1f5f9;
}

.sales-table th {
  text-align: left;
  padding: 16px;
  font-weight: 600;
  color: #34495e;
  border-bottom: 2px solid #e2e8f0;
}

/* BODY */
.sales-table td {
  padding: 16px;
  color: #2c3e50;
  border-bottom: 1px solid #edf2f7;
}

/* ZEBRA ROWS */
.sales-table tbody tr:nth-child(even) {
  background: #fafcff;
}

/* HOVER */
.sales-table tbody tr:hover {
  background: #e8f1ff;
  transform: scale(1.002);
  transition: all 0.15s ease;
}

/* FARMER NAME EMPHASIS */
.farmer-name {
  font-weight: 500;
}

/* QUANTITY BADGE STYLE */
.qty {
  font-weight: 700;
  color: #2563eb;
}

/* EXPIRY DATE */
.expiry {
  font-weight: 500;
  color: #475569;
}

</style>