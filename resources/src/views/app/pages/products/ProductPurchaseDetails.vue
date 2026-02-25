


  <template>
  <div class="main-content">
    
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card no-body v-if="!isLoading">
      <b-card-header>
        <button @click="print_product()" class="btn btn-outline-primary">
          <i class="i-Billing"></i>
          {{$t('print')}}
        </button>
      </b-card-header>
      <b-card-body>
        <b-row id="print_product">
          <b-col md="12" class="mb-5" v-if="product.type != 'is_variant'">
            <h3>{{ product.name }}</h3>
          </b-col>

          <b-col md="4" class="mt-4">
            <h3>Sales</h3>
            <table class="table table-hover table-bordered table-md">
               <thead>
                <tr>
                  <th>Product Code</th>
                  <th>Product Name</th>
                  <th>Quantity</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sale in sales" :key="sale.sale_id">
              <td>{{ sale.date }}</td>
              <td>{{ sale.client_name }}</td>
              <td>{{ sale.quantity }}</td>
            </tr>
               
              </tbody>
            </table>
          </b-col>
         

            <!-- product combo -->
            <b-col md="8" class="mt-4">
            <h3>Purchases</h3>
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>Product Date</th>
                  <th>Product Name</th>
                  <th>Quantity</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="purchase in purchases" :key="purchase.purchase_id">
              <td>{{ purchase.date }}</td>
              <td>{{ purchase.provider_name }}</td>
              <td>{{ purchase.batch_no }}</td>
            </tr>
              </tbody>
            </table>
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
      sales: [],
      purchases: []
    };
  },

  mounted() {
    this.fetchDetails();
  },

  methods: {
    fetchDetails() {
      axios.get(`/products/${this.$route.params.id}/details`)
        .then(res => {
          this.product = res.data.product;
          this.sales = res.data.sales;
          this.purchases = res.data.purchases;
        });
    }
  }
};
</script>