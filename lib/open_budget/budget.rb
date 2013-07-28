# encoding: UTF-8

require './lib/open_budget/mappers/generic'
require './lib/open_budget/mappers/canton_bern'
require './lib/open_budget/mappers/zurich'

module OpenBudget
  class Budget
    include OpenBudget::Mappers::Generic
    include OpenBudget::Mappers::CantonBern
    include OpenBudget::Mappers::Zurich

    attr_accessor :nodes, :meta, :node_index

    def initialize
      @nodes = []
      @node_index = {}
    end

    def get_node id_path
      id = id_path.join '_'
      @node_index[id]
    end

    def get_or_create_node id_path, names, intermediary = false
      id = id_path.join '_'
      node = @node_index[id] = get_node(id_path) || lambda do
        node = Node.new
        node.id = id

        if intermediary
          # allow for aggregate creation
          node.touch
        end

        # add to collection
        if id_path.length > 1
          parent = get_or_create_node id_path.take(id_path.length - 1), names.take(names.length - 1), true
          node.parent = parent
          parent.children
        else
          @nodes
        end << node

        node
      end.call

      name = names[id_path.length - 1].to_s.strip
      if name.blank?
        puts "blank name id_path #{id_path.to_s} names #{names.to_s}"
      end
      node.name = name
      node
    end

    def as_json options = nil
      @nodes.collect {|node| node.as_json(options) }
    end

    def load_meta file_path
      @meta = JSON.parse File.read(file_path)
    end

    def index_node node
      @node_index[node.id] ||= node
      node.children.each do |child|
        index_node child
      end
    end

    def load_nodes json
      nodes = JSON.parse json, {object_class: ActiveSupport::HashWithIndifferentAccess}
      nodes.each do |node_data|
        node = Node.from_hash(node_data)
        index_node node
        @nodes << node
      end
    end

    def normalize_num num, options = {}
      if options[:comma]
        comma_map = {
          :clear => '',
          :decimal => '.'
        }
        num = num.to_s.gsub(',', comma_map[options[:comma]])
      end
      num = num.to_f
      if options[:exponent]
        num = num * (10 ** options[:exponent])
      end
      num
    end

    def save_pretty_json file_path
      File.open(file_path, 'wb') do |file|
        # ToDo: fix to be able to do JSON.pretty_generate self
        file.write JSON.pretty_generate JSON.parse(self.to_json)
      end
    end

    def self.from_file file_path
      budget = new
      budget.load_nodes File.read(file_path)
      budget
    end

  end
end
