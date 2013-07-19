# encoding: UTF-8

module OpenBudget
  class Node
    attr_accessor :id, :name, :short_name, :detail, :children, :parent
    attr_reader :gross_cost, :revenue

    def initialize
      @gross_cost = BalanceCollection.new
      @revenue = BalanceCollection.new
      @children = []
    end

    def touch
      @dirty = true
      if parent
        parent.touch
      end
    end

    def name=(value)
      if @name.present? && @name.downcase != value.downcase
        warn "name diff detected #{value} for #{@name} (#{id})"
      end
      @name = value
    end

    def prepare
      if @dirty
        puts "cleaning #{id}"
        if @children.empty?
          puts "leaf node was dirty"
        else
          sum_gross_cost = BalanceCollection.new
          sum_revenue = BalanceCollection.new
          children.each do |child|
            child.prepare
            sum_gross_cost += child.gross_cost
            sum_revenue += child.revenue
          end
          if sum_gross_cost != gross_cost
            warn "gross_cost diff detected #{id} sum #{sum_gross_cost.accounts.to_s} orig #{gross_cost.accounts.to_s}"
            @gross_cost = sum_gross_cost
          end
          if sum_revenue != revenue
            warn "revenue diff detected #{id} sum #{sum_revenue.accounts.to_s} orig #{revenue.accounts.to_s}"
            @revenue = sum_revenue
          end
        end

        @dirty = false
      end
    end

    def as_hash_without_children
      hash = {
        id: id,
        name: name,
        detail: !!detail
      }
      hash[:short_name] = short_name unless short_name.blank?

      prepare
      hash[:gross_cost] = gross_cost unless gross_cost.empty?
      hash[:revenue] = revenue unless revenue.empty?
      hash
    end

    def as_json(options = nil)
      json = as_hash_without_children
      json[:children] = children unless children.empty?
      json
    end

    def self.from_hash(hash, parent = nil)
      node = new
      node.id = hash[:id]
      node.name = hash[:name]
      node.detail = hash[:detail]
      node.short_name = hash[:short_name]
      node.parent = parent

      hash[:children].to_a.each do |child|
        node.children << Node.from_hash(child, node)
      end

      node.gross_cost.load_hash hash[:gross_cost]
      node.revenue.load_hash hash[:revenue]

      node
    end

    def add_gross_cost(type, year, balance)
      if balance < 0
        add_revenue(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        @gross_cost.add(type, year, balance)
      end
    end

    def add_revenue(type, year, balance)
      if balance < 0
        add_gross_cost(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      else
        @revenue.add(type, year, balance)
      end
    end
  end
end
