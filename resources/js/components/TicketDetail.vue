<!--
TODO: Ticket Detail Component
Requirements from specification:
- /tickets/:id route - view full subject & body
- Dropdown to change category → PATCH
- Textarea for note → PATCH
- Show explanation & confidence read-only
- "Run Classification" button
- Live validation
-->

<template>
  <div class="ticket-detail">
    <div v-if="loading" class="ticket-detail__loading">
      <div class="spinner"></div>
      <p>Loading ticket...</p>
    </div>

    <div v-else-if="ticket" class="ticket-detail__content">
      <!-- Header -->
      <div class="ticket-detail__header">
        <div class="ticket-detail__breadcrumb">
          <router-link to="/tickets" class="ticket-detail__back-link">
            <ArrowLeftIcon class="ticket-detail__back-icon" />
            Back to Tickets
          </router-link>
        </div>
        
        <div class="ticket-detail__title-section">
          <h1 class="ticket-detail__title">{{ ticket.subject }}</h1>
          <span class="ticket-id">ID: {{ ticket.id }}</span>
        </div>
        
        <div class="ticket-detail__status-section">
          <span class="status-badge" :class="`status-badge--${ticket.status}`">
            {{ getStatusLabel(ticket.status) }}
          </span>
          <button
            @click="classifyTicket"
            :disabled="classifying"
            class="btn btn--primary"
          >
            <span v-if="classifying" class="spinner spinner--small"></span>
            <span v-else>
              <SparklesIcon class="btn__icon" />
              Run Classification
            </span>
          </button>
        </div>
      </div>

      <!-- Main Content -->
      <div class="ticket-detail__main">
        <!-- Left Column: Ticket Content -->
        <div class="ticket-detail__content-section">
          <div class="ticket-content">
            <h3 class="ticket-content__title">Ticket Details</h3>
            
            <div class="ticket-content__field">
              <label class="ticket-content__label">Subject</label>
              <p class="ticket-content__value">{{ ticket.subject }}</p>
            </div>
            
            <div class="ticket-content__field">
              <label class="ticket-content__label">Body</label>
              <div class="ticket-content__body">{{ ticket.body }}</div>
            </div>
            
            <div class="ticket-content__field">
              <label class="ticket-content__label">Created</label>
              <p class="ticket-content__value">{{ formatDate(ticket.created_at) }}</p>
            </div>
            
            <div class="ticket-content__field">
              <label class="ticket-content__label">Last Updated</label>
              <p class="ticket-content__value">{{ formatDate(ticket.updated_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Right Column: Classification & Actions -->
        <div class="ticket-detail__sidebar">
          <!-- Classification Section -->
          <div class="classification-panel">
            <h3 class="classification-panel__title">Classification</h3>
            
            <!-- Category -->
            <div class="form-group">
              <label class="form-group__label">Category</label>
              <select
                v-model="editableTicket.category"
                @change="updateTicketField('category', editableTicket.category)"
                class="form-group__select"
              >
                <option value="">Select category...</option>
                <option value="technical">Technical Support</option>
                <option value="billing">Billing & Payment</option>
                <option value="account">Account Management</option>
                <option value="feature_request">Feature Request</option>
                <option value="bug_report">Bug Report</option>
                <option value="general">General Inquiry</option>
              </select>
            </div>
            
            <!-- AI Classification Results (Read-only) -->
            <div v-if="ticket.confidence" class="classification-results">
              <div class="form-group">
                <label class="form-group__label">AI Confidence</label>
                <div class="confidence-display">
                  <div class="confidence-display__bar">
                    <div 
                      class="confidence-display__fill" 
                      :style="{ width: (ticket.confidence * 100) + '%' }"
                    ></div>
                  </div>
                  <span class="confidence-display__text">{{ Math.round(ticket.confidence * 100) }}%</span>
                </div>
              </div>
              
              <div v-if="ticket.explanation" class="form-group">
                <label class="form-group__label">AI Explanation</label>
                <div class="explanation-display">{{ ticket.explanation }}</div>
              </div>
            </div>
            
            <!-- Manual Note -->
            <div class="form-group">
              <label class="form-group__label">Internal Note</label>
              <textarea
                v-model="editableTicket.note"
                @blur="updateTicketField('note', editableTicket.note)"
                placeholder="Add internal notes about this ticket..."
                rows="4"
                class="form-group__textarea"
              ></textarea>
            </div>
            
            <!-- Status Change -->
            <div class="form-group">
              <label class="form-group__label">Status</label>
              <select
                v-model="editableTicket.status"
                @change="updateTicketField('status', editableTicket.status)"
                class="form-group__select"
              >
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
          </div>

          <!-- Actions Panel -->
          <div class="actions-panel">
            <h3 class="actions-panel__title">Actions</h3>
            
            <div class="actions-panel__buttons">
              <button
                @click="classifyTicket"
                :disabled="classifying"
                class="btn btn--secondary btn--full-width"
              >
                <span v-if="classifying" class="spinner spinner--small"></span>
                <span v-else>
                  <SparklesIcon class="btn__icon" />
                  Re-classify Ticket
                </span>
              </button>
              
              <button
                @click="refreshTicket"
                :disabled="loading"
                class="btn btn--outline btn--full-width"
              >
                <span v-if="loading" class="spinner spinner--small"></span>
                <span v-else>
                  <ArrowPathIcon class="btn__icon" />
                  Refresh
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else class="ticket-detail__error">
      <h2>Ticket Not Found</h2>
      <p>The requested ticket could not be found.</p>
      <router-link to="/tickets" class="btn btn--primary">
        <ArrowLeftIcon class="btn__icon" />
        Back to Tickets
      </router-link>
    </div>

    <!-- Update Notifications -->
    <div v-if="updateMessage" class="notification" :class="`notification--${updateMessage.type}`">
      {{ updateMessage.text }}
    </div>
  </div>
</template>

<script>
import { 
  ArrowLeftIcon, 
  SparklesIcon, 
  ArrowPathIcon 
} from '@heroicons/vue/24/outline'

export default {
  name: 'TicketDetail',
  components: {
    ArrowLeftIcon,
    SparklesIcon,
    ArrowPathIcon
  },
  props: {
    id: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      ticket: null,
      editableTicket: {},
      loading: true,
      classifying: false,
      updating: false,
      updateMessage: null,
      updateTimeout: null
    }
  },
  async mounted() {
    await this.fetchTicket();
  },
  watch: {
    id: {
      immediate: false,
      async handler() {
        await this.fetchTicket();
      }
    }
  },
  methods: {
    async fetchTicket() {
      try {
        this.loading = true;
        const response = await fetch(`/api/tickets/${this.id}`);
        
        if (!response.ok) {
          if (response.status === 404) {
            this.ticket = null;
            return;
          }
          throw new Error('Failed to fetch ticket');
        }
        
        const data = await response.json();
        this.ticket = data.data || data;
        
        // Initialize editable copy
        this.editableTicket = {
          category: this.ticket.category || '',
          note: this.ticket.note || '',
          status: this.ticket.status
        };
        
      } catch (error) {
        console.error('Error fetching ticket:', error);
        this.showUpdateMessage('Failed to load ticket', 'error');
      } finally {
        this.loading = false;
      }
    },
    
    async refreshTicket() {
      await this.fetchTicket();
      this.showUpdateMessage('Ticket refreshed', 'success');
    },
    
    async classifyTicket() {
      if (this.classifying) return;
      
      try {
        this.classifying = true;
        
        const response = await fetch(`/api/tickets/${this.id}/classify`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          }
        });
        
        if (!response.ok) throw new Error('Classification failed');
        
        this.showUpdateMessage('Classification started...', 'info');
        
        // Poll for classification completion
        await this.pollForClassificationCompletion();
        
      } catch (error) {
        console.error('Error classifying ticket:', error);
        this.showUpdateMessage('Failed to classify ticket', 'error');
      } finally {
        this.classifying = false;
      }
    },

    async pollForClassificationCompletion() {
      const maxAttempts = 30; // 30 seconds max
      const pollInterval = 1000; // 1 second
      let attempts = 0;
      
      while (attempts < maxAttempts) {
        try {
          const response = await fetch(`/api/tickets/${this.id}`);
          if (!response.ok) throw new Error('Failed to fetch ticket');
          
          const data = await response.json();
          const ticket = data.data || data;
          
          // Check if classification is complete (has category and confidence)
          if (ticket.category && ticket.confidence !== null) {
            // Classification complete, update the ticket data
            this.ticket = ticket;
            this.editableTicket = {
              category: this.ticket.category || '',
              note: this.ticket.note || '',
              status: this.ticket.status
            };
            this.showUpdateMessage('Classification completed successfully!', 'success');
            return;
          }
          
          // Wait before next poll
          await new Promise(resolve => setTimeout(resolve, pollInterval));
          attempts++;
          
        } catch (error) {
          console.error('Error polling for classification:', error);
          break;
        }
      }
      
      // If we reach here, classification took too long or failed
      console.warn('Classification polling timeout for ticket:', this.id);
      this.showUpdateMessage('Classification is taking longer than expected...', 'warning');
      await this.fetchTicket(); // Refresh anyway to show current state
    },
    
    async updateTicketField(field, value) {
      // Don't update if value hasn't actually changed
      if (this.ticket[field] === value) return;
      
      try {
        this.updating = true;
        
        const updateData = { [field]: value };
        
        const response = await fetch(`/api/tickets/${this.id}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
          },
          body: JSON.stringify(updateData)
        });
        
        if (!response.ok) {
          const errorData = await response.json();
          throw new Error(errorData.message || 'Update failed');
        }
        
        const updatedTicket = await response.json();
        this.ticket = updatedTicket.data || updatedTicket;
        
        // Update editable copy
        this.editableTicket[field] = this.ticket[field];
        
        this.showUpdateMessage(`${this.capitalizeFirst(field)} updated successfully`, 'success');
        
      } catch (error) {
        console.error(`Error updating ${field}:`, error);
        this.showUpdateMessage(`Failed to update ${field}`, 'error');
        
        // Revert the editable field to the original value
        this.editableTicket[field] = this.ticket[field];
      } finally {
        this.updating = false;
      }
    },
    
    showUpdateMessage(text, type = 'info') {
      this.updateMessage = { text, type };
      
      // Clear any existing timeout
      if (this.updateTimeout) {
        clearTimeout(this.updateTimeout);
      }
      
      // Auto-hide after 3 seconds
      this.updateTimeout = setTimeout(() => {
        this.updateMessage = null;
      }, 3000);
    },
    
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      const date = new Date(dateString);
      return date.toLocaleString();
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
    
    capitalizeFirst(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    }
  },
  
  beforeUnmount() {
    if (this.updateTimeout) {
      clearTimeout(this.updateTimeout);
    }
  }
}
</script>
