


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

         
         

            <!-- product combo -->
            <b-col md="12" class="mt-4">
            <h3>Purchases</h3>
            <table class="table table-hover table-bordered table-md">
              <thead>
                <tr>
                  <th>Product Date</th>
                  <th>Supplier</th>
                  <th>Quantity</th>
                  <th>Batch Number</th>
                  <th>Expiry Date</th>
                  <th>Warehouse</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="purchase in purchases" :key="purchase.purchase_id">
              <td>{{ purchase.date }}</td>
              <td>{{ purchase.provider_name }}</td>
              <td>{{ purchase.quantity }}</td>
              <td>{{ purchase.batch_no }}</td>
              <td>{{ purchase.expiry_date }}</td>
              <td>{{ purchase.warehouse_name }}</td>
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
          this.purchases = res.data.purchases;
        });
    }
  }
};
</script>