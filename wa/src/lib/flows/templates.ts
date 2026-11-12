/**
 * Starter flow templates.
 *
 * Three pre-canned flows users can clone with one click instead of
 * building from scratch. Each template is a plain JS object describing
 * the same shape `/api/flows` PUT accepts — name, trigger config,
 * entry_node_id, fallback_policy, nodes[] — keyed by a stable
 * `slug`.
 *
 * The clone path (`/api/flows` POST with `template_slug`) creates a
 * NEW flow_row + flow_nodes rows for the user. `node_key`s are kept
 * verbatim (they're stable strings, not UUIDs, so cloning never
 * needs to rewrite edge references).
 *
 * Choosing a single static module over a DB-backed gallery for v1
 * because: (a) the set is small and changes with code releases, not
 * data; (b) keeps templates portable across self-hosted instances
 * without migrations; (c) editing in source is the lowest-friction
 * way to add the next template.
 */

import type {
  CollectInputNodeConfig,
  ConditionNodeConfig,
  HandoffNodeConfig,
  KeywordTriggerConfig,
  SendButtonsNodeConfig,
  SendListNodeConfig,
  SendMessageNodeConfig,
  StartNodeConfig,
} from "./types";

export type FlowTemplateNodeType =
  | "start"
  | "send_message"
  | "send_buttons"
  | "send_list"
  | "collect_input"
  | "condition"
  | "set_tag"
  | "handoff"
  | "end";

export interface FlowTemplateNode {
  node_key: string;
  node_type: FlowTemplateNodeType;
  config:
    | StartNodeConfig
    | SendMessageNodeConfig
    | SendButtonsNodeConfig
    | SendListNodeConfig
    | CollectInputNodeConfig
    | ConditionNodeConfig
    | HandoffNodeConfig
    | Record<string, unknown>;
}

export interface FlowTemplate {
  slug: string;
  name: string;
  description: string;
  /** Used by the gallery to surface a relevant icon. lucide-react name. */
  icon: "MessageSquare" | "HelpCircle" | "UserPlus";
  trigger_type: "keyword" | "first_inbound_message" | "manual";
  trigger_config: KeywordTriggerConfig | Record<string, unknown>;
  entry_node_id: string;
  nodes: FlowTemplateNode[];
}

// ============================================================
// 1. Welcome menu — the example from the owner's brief
// ============================================================
const WELCOME_MENU: FlowTemplate = {
  slug: "welcome_menu",
  name: "Welcome menu",
  description:
    "Greet customers who type a keyword and route them to the right agent based on whether they're new or existing.",
  icon: "MessageSquare",
  trigger_type: "keyword",
  trigger_config: { keywords: ["support", "help", "hi"], match_type: "contains" },
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "welcome" },
    },
    {
      node_key: "welcome",
      node_type: "send_buttons",
      config: {
        text: "Hi! 👋 Welcome to support. Are you an existing customer or new here?",
        footer_text: "Tap a button below to continue.",
        buttons: [
          {
            reply_id: "existing",
            title: "Existing customer",
            next_node_key: "existing_handoff",
          },
          {
            reply_id: "new",
            title: "New customer",
            next_node_key: "new_handoff",
          },
        ],
      } as SendButtonsNodeConfig,
    },
    {
      node_key: "existing_handoff",
      node_type: "handoff",
      config: {
        note: "Existing customer needs assistance — please check account history before replying.",
      } as HandoffNodeConfig,
    },
    {
      node_key: "new_handoff",
      node_type: "handoff",
      config: {
        note: "New customer — share pricing + onboarding link.",
      } as HandoffNodeConfig,
    },
  ],
};

// ============================================================
// 2. FAQ bot — list-message answers, fully automated
// ============================================================
const FAQ_BOT: FlowTemplate = {
  slug: "faq_bot",
  name: "FAQ bot",
  description:
    "Answer common questions automatically. Customer picks a topic from a list; the bot replies with the answer and ends.",
  icon: "HelpCircle",
  trigger_type: "keyword",
  trigger_config: {
    keywords: ["faq", "question", "info"],
    match_type: "contains",
  },
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "topics" },
    },
    {
      node_key: "topics",
      node_type: "send_list",
      config: {
        text: "What can I help you with?",
        button_label: "View topics",
        sections: [
          {
            title: "Common questions",
            rows: [
              {
                reply_id: "hours",
                title: "Opening hours",
                next_node_key: "answer_hours",
              },
              {
                reply_id: "pricing",
                title: "Pricing",
                next_node_key: "answer_pricing",
              },
              {
                reply_id: "refunds",
                title: "Refund policy",
                next_node_key: "answer_refunds",
              },
            ],
          },
          {
            title: "Other",
            rows: [
              {
                reply_id: "human",
                title: "Talk to a human",
                next_node_key: "human_handoff",
              },
            ],
          },
        ],
      } as SendListNodeConfig,
    },
    {
      node_key: "answer_hours",
      node_type: "send_message",
      config: {
        text: "We're open Mon–Fri, 9am–6pm local time. Weekend support is limited to urgent issues.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "answer_pricing",
      node_type: "send_message",
      config: {
        text: "Our pricing starts at $9/mo. Visit https://example.com/pricing for the full breakdown.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "answer_refunds",
      node_type: "send_message",
      config: {
        text: "Refunds are honored within 30 days of purchase. Reply with your order number and we'll process it.",
        next_node_key: "end",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "human_handoff",
      node_type: "handoff",
      config: {
        note: "Customer asked to talk to a human from the FAQ bot.",
      } as HandoffNodeConfig,
    },
    {
      node_key: "end",
      node_type: "end",
      config: {},
    },
  ],
};

// ============================================================
// 3. Lead capture — collect_input chain, ends in a handoff
// ============================================================
const LEAD_CAPTURE: FlowTemplate = {
  slug: "lead_capture",
  name: "Lead capture",
  description:
    "Greet first-time inbounds, capture name + email + company, then hand off to sales with the answers in the note.",
  icon: "UserPlus",
  trigger_type: "first_inbound_message",
  trigger_config: {},
  entry_node_id: "start",
  nodes: [
    {
      node_key: "start",
      node_type: "start",
      config: { next_node_key: "intro" },
    },
    {
      node_key: "intro",
      node_type: "send_message",
      config: {
        text: "Welcome! 👋 I'll ask a few quick questions so we can get you to the right person.",
        next_node_key: "ask_name",
      } as SendMessageNodeConfig,
    },
    {
      node_key: "ask_name",
      node_type: "collect_input",
      config: {
        prompt_text: "What's your name?",
        var_key: "name",
        next_node_key: "ask_email",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_email",
      node_type: "collect_input",
      config: {
        prompt_text: "Thanks {{vars.name}}! What's your work email?",
        var_key: "email",
        next_node_key: "ask_company",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "ask_company",
      node_type: "collect_input",
      config: {
        prompt_text: "Almost done — what's your company name?",
        var_key: "company",
        next_node_key: "handoff",
      } as CollectInputNodeConfig,
    },
    {
      node_key: "handoff",
      node_type: "handoff",
      config: {
        note: "New lead — name={{vars.name}}, email={{vars.email}}, company={{vars.company}}.",
      } as HandoffNodeConfig,
    },
  ],
};

// ============================================================
// Registry
// ============================================================

// ============================================================
// 4. Bhardwaj Financial Services — Direct Loan & BT Qualification
// ============================================================
const BFS_LOAN_QUALIFICATION: FlowTemplate = {
  "slug": "bfs_loan_qualification",
  "name": "BFS Loan & BT Qualification",
  "description": "Bhardwaj Financial Services direct-lender greeting in professional tone with Interested/Not Interested buttons, all Loan types + Balance Transfer (BT) branching, requirement capture, and city qualification.",
  "icon": "UserPlus",
  "trigger_type": "first_inbound_message",
  "trigger_config": {},
  "entry_node_id": "start",
  "nodes": [
    {
      "node_key": "start",
      "node_type": "start",
      "config": {
        "next_node_key": "welcome_interest"
      }
    },
    {
      "node_key": "welcome_interest",
      "node_type": "send_buttons",
      "config": {
        "header_text": "Bhardwaj Financial Services",
        "text": "Dear {{contact.first_name}} ji,\n\nWelcome to *Bhardwaj Financial Services*. Through our dedicated Credit Desk, we directly provide Home Loans, Loan Against Property (LAP), Business Loans, Personal Loans, and Balance Transfer (BT) facilities with transparent terms and competitive interest rates.\n\nWould you like to explore a new Loan or Balance Transfer (BT) requirement with us?",
        "footer_text": "Credit Desk • +91 92587-24227",
        "buttons": [
          {
            "reply_id": "interested",
            "title": "Interested",
            "next_node_key": "choose_loan_type"
          },
          {
            "reply_id": "not_interested",
            "title": "Not Interested",
            "next_node_key": "not_interested_msg"
          }
        ]
      }
    },
    {
      "node_key": "not_interested_msg",
      "node_type": "send_message",
      "config": {
        "text": "Thank you for your time, {{contact.first_name}} ji. Whenever you have any Loan or Balance Transfer requirement in the future, please feel free to reach out to us on WhatsApp (+91 79009-79001) or call +91 92587-24227. Have a pleasant day ahead.",
        "next_node_key": "end_not_interested"
      }
    },
    {
      "node_key": "end_not_interested",
      "node_type": "end",
      "config": {}
    },
    {
      "node_key": "choose_loan_type",
      "node_type": "send_list",
      "config": {
        "header_text": "Loan & BT Services",
        "text": "Thank you, {{contact.first_name}} ji.\n\nPlease select the Loan or Balance Transfer (BT) category you are looking for:",
        "footer_text": "Select an option below",
        "button_label": "Select Loan Type",
        "sections": [
          {
            "title": "Property & Top-Up Loans",
            "rows": [
              {
                "reply_id": "home_loan",
                "title": "Home Loan",
                "description": "Home purchase, construction, or extension",
                "next_node_key": "ask_amount"
              },
              {
                "reply_id": "lap_loan",
                "title": "Loan Against Property",
                "description": "Residential or commercial property mortgage",
                "next_node_key": "ask_amount"
              },
              {
                "reply_id": "topup_loan",
                "title": "Top-Up Loan",
                "description": "Additional funding on an existing loan",
                "next_node_key": "ask_amount"
              }
            ]
          },
          {
            "title": "Business & Personal",
            "rows": [
              {
                "reply_id": "business_loan",
                "title": "Business Loan",
                "description": "Working capital, MSME & business expansion",
                "next_node_key": "ask_amount"
              },
              {
                "reply_id": "personal_loan",
                "title": "Personal Loan",
                "description": "For salaried & self-employed professionals",
                "next_node_key": "ask_amount"
              },
              {
                "reply_id": "auto_loan",
                "title": "Car / Auto Loan",
                "description": "New or pre-owned vehicle financing",
                "next_node_key": "ask_amount"
              }
            ]
          },
          {
            "title": "Balance Transfer (BT)",
            "rows": [
              {
                "reply_id": "balance_transfer",
                "title": "Balance Transfer (BT)",
                "description": "Lower your existing ROI with optional Top-Up",
                "next_node_key": "choose_bt_type"
              }
            ]
          }
        ]
      }
    },
    {
      "node_key": "choose_bt_type",
      "node_type": "send_list",
      "config": {
        "header_text": "Balance Transfer (BT)",
        "text": "Certainly, {{contact.first_name}} ji. We offer Balance Transfer with lower interest rates and optional Top-Up funding.\n\nPlease select your existing loan category for Balance Transfer:",
        "footer_text": "Select BT loan type",
        "button_label": "Select BT Loan Type",
        "sections": [
          {
            "title": "BT Loan Category",
            "rows": [
              {
                "reply_id": "bt_home",
                "title": "Home Loan BT",
                "description": "Transfer existing Home Loan for lower ROI",
                "next_node_key": "ask_bt_details"
              },
              {
                "reply_id": "bt_lap",
                "title": "LAP / Property BT",
                "description": "Balance Transfer of Property Loan + Top-Up",
                "next_node_key": "ask_bt_details"
              },
              {
                "reply_id": "bt_business",
                "title": "Business Loan BT",
                "description": "Restructure or transfer Business Loan",
                "next_node_key": "ask_bt_details"
              },
              {
                "reply_id": "bt_personal",
                "title": "Personal Loan BT",
                "description": "Shift Personal Loan for reduced monthly EMI",
                "next_node_key": "ask_bt_details"
              }
            ]
          }
        ]
      }
    },
    {
      "node_key": "ask_bt_details",
      "node_type": "collect_input",
      "config": {
        "prompt_text": "Please share your approximate remaining Balance Transfer (BT) amount and whether you also need a Top-Up (for example: 25 Lakh BT + 5 Lakh Top-Up):",
        "var_key": "loan_amount",
        "next_node_key": "ask_work_profile"
      }
    },
    {
      "node_key": "ask_amount",
      "node_type": "collect_input",
      "config": {
        "prompt_text": "Thank you, {{contact.first_name}} ji. What approximate loan amount are you looking for? (For example: 10 Lakh, 25 Lakh, 1 Crore)",
        "var_key": "loan_amount",
        "next_node_key": "ask_work_profile"
      }
    },
    {
      "node_key": "ask_work_profile",
      "node_type": "send_buttons",
      "config": {
        "text": "Noted (Requirement: {{vars.loan_amount}}).\n\nPlease select your employment / work profile so our Credit Team can assess the right eligibility and terms:",
        "buttons": [
          {
            "reply_id": "salaried",
            "title": "Salaried (Job)",
            "next_node_key": "ask_city"
          },
          {
            "reply_id": "business",
            "title": "Business / Self-Emp",
            "next_node_key": "ask_city"
          }
        ]
      }
    },
    {
      "node_key": "ask_city",
      "node_type": "collect_input",
      "config": {
        "prompt_text": "One final detail, {{contact.first_name}} ji — please share your City / Location (and property city, if applicable):",
        "var_key": "city",
        "next_node_key": "final_confirm"
      }
    },
    {
      "node_key": "final_confirm",
      "node_type": "send_message",
      "config": {
        "text": "Thank you, {{contact.first_name}} ji. Your requirement has been registered with our Credit Desk:\n\n• Requirement: {{vars.loan_amount}}\n• Location: {{vars.city}}\n\nOur Credit Desk team will review your request and connect with you shortly via Call (+91 92587-24227) or WhatsApp (+91 79009-79001).\n\nIf you have any questions regarding interest rates, EMI, eligibility, or documents, please feel free to ask here in English or Hindi.",
        "next_node_key": "end_qualified"
      }
    },
    {
      "node_key": "end_qualified",
      "node_type": "end",
      "config": {}
    }
  ]
};

const TEMPLATES: Record<string, FlowTemplate> = {
  bfs_loan_qualification: BFS_LOAN_QUALIFICATION,
  welcome_menu: WELCOME_MENU,
  faq_bot: FAQ_BOT,
  lead_capture: LEAD_CAPTURE,
};

export function getFlowTemplate(slug: string): FlowTemplate | null {
  return TEMPLATES[slug] ?? null;
}

export function listFlowTemplates(): FlowTemplate[] {
  return Object.values(TEMPLATES);
}
